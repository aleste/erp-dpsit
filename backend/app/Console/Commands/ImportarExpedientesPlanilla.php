<?php

namespace App\Console\Commands;

use App\Models\DependenciaDestino;
use App\Models\Expediente;
use App\Models\ExpedienteEstado;
use App\Models\ExpedienteIntervencion;
use App\Models\ExpedienteTipoRequerimiento;
use App\Models\Ministerio;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Migra la hoja de la planilla «MOVIMIENTOS DE EXPEDIENTES» (exportada a CSV) al módulo de expedientes.
 *
 * - Cada fila es una intervención (un pase por el área); las filas se agrupan por número de expediente.
 * - Idempotente: una fila cuyo expediente ya tiene una intervención con la misma fecha de ingreso se omite.
 * - No inventa datos: solo crea movimientos con fechas que existen en la planilla; lo que no puede
 *   interpretarse queda vacío, con el valor original en observaciones y una advertencia en el informe.
 * - Con --dry-run no escribe nada en la base y genera el mismo informe.
 */
class ImportarExpedientesPlanilla extends Command
{
    protected $signature = 'expedientes:importar
                            {archivo : CSV de la hoja (fila de encabezados como en la planilla)}
                            {--organismos= : CSV con la columna ORGANISMO (hoja «ORGANISMOS 2025»)}
                            {--equivalencias= : CSV nombre;email que vincula los nombres de la planilla con usuarios}
                            {--hoja=2026 : Nombre de la hoja de origen (y año para corregir años mal tipeados)}
                            {--fila-inicial=9 : Fila de la planilla donde está el encabezado, para referenciar filas}
                            {--dry-run : Simula la importación sin escribir en la base}';

    protected $description = 'Importa la planilla de seguimiento de expedientes (CSV) con informe de verificación';

    private const RE_GEDO = '/\b(?:IF|PV|NO|ME|RESOL|DISPO|CONVE|ACTA|INLEG)-\d{4}-\d{6,9}-\s*-?\s*GDEBA-[A-Z0-9]+/i';

    private const COLUMNAS = [
        'ingreso' => 'Ingreso', 'egreso' => 'Egreso', 'numero' => 'Nº Expediente Electrónico', 'organismo' => 'Organismo',
        'descripcion' => 'Descripción', 'responsables' => 'EE analizado por:', 'requerimiento' => 'Requerimiento',
        'estado' => 'Estado', 'etb' => 'ETB', 'revision' => 'A revisión', 'revisor' => 'Envíado a:', 'firma' => 'Firma',
        'destino' => 'Remitido a:', 'observaciones' => 'Observaciones', 'acto' => 'C/ acto administrativo',
    ];

    private array $informe = [];

    private array $resumen = [];

    private array $sinEquivalencia = [];

    // Columna lógica => clave del encabezado real del CSV
    private array $columnas = [];

    private bool $dryRun = false;

    private string $hoja;

    private int $anioHoja;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->hoja = (string) $this->option('hoja');
        $this->anioHoja = (int) (preg_match('/(20\d{2})/', $this->hoja, $m) ? $m[1] : now()->year);

        $filas = $this->leerCsv($this->argument('archivo'));
        if ($filas === null) {
            return self::FAILURE;
        }

        $this->resolverColumnas(array_keys(reset($filas) ?: []));
        if (! isset($this->columnas['numero'], $this->columnas['ingreso'])) {
            $this->error('El CSV no tiene las columnas «Nº Expediente Electrónico» e «Ingreso». Exporte la hoja desde la fila de encabezados.');

            return self::FAILURE;
        }

        $this->info(($this->dryRun ? '[SIMULACIÓN] ' : '').'Filas leídas: '.count($filas));

        $organismos = $this->importarOrganismos();
        $usuarios = $this->leerEquivalencias();
        $estados = $this->indice(ExpedienteEstado::all(), 'nombre');
        $requerimientos = $this->indice(ExpedienteTipoRequerimiento::all(), 'nombre');
        $estadoInicial = ExpedienteEstado::where('es_final', false)->where('activo', true)->orderBy('orden')->first();
        $estadoSalida = ExpedienteEstado::where('es_final', true)->where('activo', true)->orderBy('orden')->first();

        if (! $estadoInicial || ! $estadoSalida) {
            $this->error('Faltan los catálogos de estados. Ejecute: php artisan db:seed --class=ExpedientesCatalogoSeeder');

            return self::FAILURE;
        }

        // 1. Interpretar cada fila
        $registros = [];
        foreach ($filas as $n => $fila) {
            $registro = $this->interpretarFila($fila, $n, $organismos, $usuarios, $estados, $requerimientos, $estadoInicial, $estadoSalida);
            if ($registro) {
                $registros[] = $registro;
            }
        }

        // 2. Destinos («Remitido a»): agrupación de variantes por clave normalizada
        $destinos = $this->agruparDestinos($registros);

        // 3. Agrupar por expediente e importar
        $porExpediente = collect($registros)->groupBy('numero');
        foreach ($porExpediente as $numero => $grupo) {
            $this->importarExpediente($numero, $grupo->sortBy([['fecha_ingreso', 'asc'], ['fila', 'asc']])->values()->all(), $destinos);
        }

        $this->escribirInforme($destinos, $this->sinEquivalencia);

        if (! $this->dryRun) {
            AuditService::log('IMPORT', 'EXPEDIENTES', null, null, $this->resumen, "Importación de planilla (hoja {$this->hoja})");
        }

        return self::SUCCESS;
    }

    private function interpretarFila(array $fila, int $n, array $organismos, array $usuarios, array $estados, array $requerimientos, ExpedienteEstado $estadoInicial, ExpedienteEstado $estadoSalida): ?array
    {
        $filaPlanilla = $n + (int) $this->option('fila-inicial') + 1;
        $notas = [];
        $v = fn (string $col) => trim((string) ($fila[$this->columnas[$col] ?? ''] ?? ''));

        $numero = $v('numero');
        if ($numero === '') {
            $this->advertir($filaPlanilla, 'numero', 'Fila sin número de expediente: se omite', 'omitida');

            return null;
        }

        $ingreso = $this->fecha($v('ingreso'), $filaPlanilla, 'Ingreso', $notas);
        if (! $ingreso) {
            $this->advertir($filaPlanilla, 'Ingreso', 'Fecha de ingreso vacía o ilegible: se omite la fila', 'omitida');

            return null;
        }

        $egreso = $this->fecha($v('egreso'), $filaPlanilla, 'Egreso', $notas, $ingreso);
        $revision = $this->fecha($v('revision'), $filaPlanilla, 'A revisión', $notas, $ingreso);
        $firma = $this->fecha($v('firma'), $filaPlanilla, 'Firma', $notas, $ingreso);

        // Estado: catálogo; sin estado se deduce solo lo inequívoco (con egreso = salida)
        $estadoTexto = $v('estado');
        $estado = $estados[$this->clave($estadoTexto)] ?? null;
        if (! $estado) {
            if ($estadoTexto !== '') {
                $this->advertir($filaPlanilla, 'Estado', "Estado «{$estadoTexto}» no existe en el catálogo");
                $notas[] = "Estado en planilla: {$estadoTexto}";
            }
            $estado = ($v('egreso') !== '' || $egreso) ? $estadoSalida : $estadoInicial;
            if ($estadoTexto === '') {
                $this->advertir($filaPlanilla, 'Estado', 'Sin estado: se asigna «'.$estado->nombre.'»');
            }
        }

        $enArea = ! $estado->es_final && ! $egreso && $v('egreso') === '';
        if ($estado->es_final && ! $egreso) {
            $this->advertir($filaPlanilla, 'Egreso', 'Estado de salida sin fecha de egreso válida: se importa como egresado sin fecha');
        }
        if (! $estado->es_final && ($egreso || $v('egreso') !== '')) {
            $this->advertir($filaPlanilla, 'Estado', "Tiene egreso pero el estado es «{$estado->nombre}»: se importa como egresado");
        }

        // Responsables: «A, B» o «A / B» → responsable A + colaborador B
        $nombres = array_values(array_filter(array_map('trim', preg_split('/\s*(?:,|\/|\sy\s)\s*/u', $v('responsables')))));
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = $usuarios['mapa'][$this->clave($nombre)] ?? null;
            if ($id) {
                $ids[] = $id;
            } else {
                $this->sinEquivalencia[$nombre] = ($this->sinEquivalencia[$nombre] ?? 0) + 1;
                $notas[] = "Analizado por (planilla): {$nombre}";
            }
        }
        if ($nombres && ! $ids) {
            $this->advertir($filaPlanilla, 'EE analizado por', 'Responsable sin usuario equivalente: queda sin responsable');
        }

        $revisorNombre = $v('revisor');
        $revisorId = $revisorNombre !== '' ? ($usuarios['mapa'][$this->clave($revisorNombre)] ?? null) : null;
        if ($revisorNombre !== '' && ! $revisorId) {
            $this->sinEquivalencia[$revisorNombre] = ($this->sinEquivalencia[$revisorNombre] ?? 0) + 1;
            $notas[] = "Enviado a (planilla): {$revisorNombre}";
        }

        $organismoTexto = $v('organismo');
        $ministerioId = $organismoTexto !== '' ? ($organismos[$this->clave($organismoTexto)] ?? null) : null;
        if ($organismoTexto !== '' && ! $ministerioId) {
            $this->advertir($filaPlanilla, 'Organismo', "Organismo «{$organismoTexto}» sin equivalente en el catálogo");
            $notas[] = "Organismo (planilla): {$organismoTexto}";
        }

        $reqTexto = $v('requerimiento');
        $requerimiento = $reqTexto !== '' ? ($requerimientos[$this->clave($reqTexto)] ?? null) : null;
        if ($reqTexto !== '' && ! $requerimiento) {
            $this->advertir($filaPlanilla, 'Requerimiento', "Requerimiento «{$reqTexto}» sin equivalente en el catálogo");
            $notas[] = "Requerimiento (planilla): {$reqTexto}";
        }

        // Observaciones: el número de GEDO pasa a documento de respuesta; el resto del texto se conserva
        $observaciones = $v('observaciones');
        $documento = null;
        if (preg_match_all(self::RE_GEDO, $observaciones, $m) && $m[0]) {
            $documento = mb_strtoupper(preg_replace('/\s+/', ' ', $m[0][0]));
            $observaciones = trim(preg_replace(self::RE_GEDO, '', $observaciones, 1), " \t\n\r-–.;,");
        }
        $observaciones = trim(implode("\n", array_filter([$observaciones, ...$notas])));

        $acto = match ($this->clave($v('acto'))) {
            'si' => 'SI', 'no corresponde' => 'NO_CORRESPONDE', 'pendiente' => 'PENDIENTE', default => null,
        };

        $this->contar('filas_validas');
        $this->contar('estado_planilla:'.($estadoTexto !== '' ? $estadoTexto : '(vacío)'));

        return [
            'fila' => $filaPlanilla,
            'numero' => Expediente::normalizarNumero($numero),
            'descripcion' => $v('descripcion') ?: null,
            'ministerio_id' => $ministerioId,
            'fecha_ingreso' => $ingreso->toDateString(),
            'fecha_egreso' => $egreso?->toDateString(),
            'en_area' => $enArea,
            'estado' => $estado,
            'tipo_requerimiento_id' => $requerimiento?->id,
            'responsable_id' => $ids[0] ?? null,
            'colaboradores' => array_slice($ids, 1),
            'responsables_texto' => $v('responsables'),
            'revisor_id' => $revisorId,
            'revisor_texto' => $revisorNombre,
            'fecha_envio_revision' => $revision?->toDateString(),
            'fecha_firma' => $firma?->toDateString(),
            'destino_texto' => preg_replace('/\s+/', ' ', $v('destino')),
            'documento_respuesta' => $documento,
            'etb' => $v('etb') ?: null,
            'con_acto_administrativo' => $acto,
            'observaciones' => $observaciones ?: null,
        ];
    }

    private function importarExpediente(string $numero, array $filas, array $destinos): void
    {
        $existente = Expediente::where('numero', $numero)->first();
        $fechasExistentes = $existente
            ? $existente->intervenciones()->withTrashed()->pluck('fecha_ingreso')->map(fn ($f) => $f->toDateString())->all()
            : [];

        // Omitir filas ya importadas o repetidas (mismo expediente y misma fecha de ingreso)
        $vistas = [];
        $nuevas = [];
        foreach ($filas as $f) {
            if (in_array($f['fecha_ingreso'], $fechasExistentes, true)) {
                $this->advertir($f['fila'], 'numero', 'Ya importada (mismo expediente y fecha de ingreso): se omite', 'ya_importada');

                continue;
            }
            if (isset($vistas[$f['fecha_ingreso']])) {
                $this->advertir($f['fila'], 'numero', "Fila duplicada de la fila {$vistas[$f['fecha_ingreso']]} (mismo expediente y fecha de ingreso): se omite", 'duplicada');

                continue;
            }
            $vistas[$f['fecha_ingreso']] = $f['fila'];
            $nuevas[] = $f;
        }
        if (! $nuevas) {
            return;
        }

        // Solo la última intervención puede quedar abierta; un ingreso posterior implica que la anterior salió
        $abiertaEnBase = $existente?->intervenciones()->where('en_area', true)->exists();
        foreach ($nuevas as $i => &$f) {
            $hayPosterior = $i < count($nuevas) - 1;
            if ($f['en_area'] && ($hayPosterior || $abiertaEnBase)) {
                $f['en_area'] = false;
                $f['observaciones'] = trim(($f['observaciones'] ?? '')."\nImportada como egresada: hay un ingreso posterior del mismo expediente.");
                $this->advertir($f['fila'], 'Egreso', 'Sin egreso pero hay otro ingreso posterior del expediente: se importa como egresada sin fecha');
            }
        }
        unset($f);

        $this->contar($existente ? 'expedientes_existentes' : 'expedientes_nuevos');
        $this->contar('intervenciones_importadas', count($nuevas));
        foreach ($nuevas as $f) {
            $this->contar('intervenciones_abiertas', $f['en_area'] ? 1 : 0);
            $this->contar('estado_importado:'.$f['estado']->nombre);
        }

        if ($this->dryRun) {
            return;
        }

        try {
            DB::transaction(function () use ($numero, $nuevas, $existente, $destinos) {
                $expediente = $existente ?? Expediente::create(Expediente::atributosDeNumero($numero) + [
                    'caratula' => $nuevas[0]['descripcion'],
                    'ministerio_id' => $nuevas[0]['ministerio_id'],
                ]);
                $siguiente = (int) $expediente->intervenciones()->withTrashed()->max('numero_intervencion') + 1;

                foreach ($nuevas as $f) {
                    $destino = $f['destino_texto'] !== '' ? $this->destino($destinos, $f['destino_texto']) : null;
                    $intervencion = $expediente->intervenciones()->create([
                        'numero_intervencion' => $siguiente++,
                        'fecha_ingreso' => $f['fecha_ingreso'],
                        'fecha_egreso' => $f['fecha_egreso'],
                        'en_area' => $f['en_area'],
                        'estado_id' => $f['estado']->id,
                        'tipo_requerimiento_id' => $f['tipo_requerimiento_id'],
                        'descripcion' => $f['descripcion'],
                        'responsable_id' => $f['responsable_id'],
                        'revisor_id' => $f['revisor_id'],
                        'fecha_envio_revision' => $f['fecha_envio_revision'],
                        'fecha_firma' => $f['fecha_firma'],
                        'dependencia_destino_id' => $destino?->id,
                        'documento_respuesta' => $f['documento_respuesta'],
                        'etb' => $f['etb'],
                        'con_acto_administrativo' => $f['con_acto_administrativo'],
                        'observaciones' => $f['observaciones'],
                        'origen' => 'PLANILLA',
                        'origen_referencia' => "{$this->hoja}: fila {$f['fila']}",
                    ]);
                    $intervencion->colaboradores()->sync($f['colaboradores']);
                    $this->movimientosImportados($intervencion, $f, $destino);
                }
            });
        } catch (Throwable $e) {
            foreach ($nuevas as $f) {
                $this->advertir($f['fila'], 'numero', 'Error al importar el expediente: '.$e->getMessage(), 'error');
            }
        }
    }

    /**
     * Movimientos reconstruidos SOLO con las fechas presentes en la planilla (sin inventar pasos)
     */
    private function movimientosImportados(ExpedienteIntervencion $intervencion, array $f, ?DependenciaDestino $destino): void
    {
        $base = ['importado' => true, 'user_id' => null];
        $intervencion->movimientos()->create($base + [
            'tipo' => 'INGRESO',
            'fecha' => $f['fecha_ingreso'],
            'estado_nuevo_id' => $f['en_area'] ? $f['estado']->id : null,
            'descripcion' => "Importado de la planilla (hoja {$this->hoja}, fila {$f['fila']}).".
                ($f['responsables_texto'] !== '' ? " Analizado por: {$f['responsables_texto']}." : '').
                ($f['en_area'] ? " Estado al importar: {$f['estado']->nombre}." : ''),
        ]);
        if ($f['fecha_envio_revision']) {
            $intervencion->movimientos()->create($base + [
                'tipo' => 'ENVIO_REVISION',
                'fecha' => $f['fecha_envio_revision'],
                'descripcion' => 'Enviado a revisión'.($f['revisor_texto'] !== '' ? " de {$f['revisor_texto']}" : '').' (planilla).',
            ]);
        }
        if ($f['fecha_firma']) {
            $intervencion->movimientos()->create($base + [
                'tipo' => 'FIRMA',
                'fecha' => $f['fecha_firma'],
                'descripcion' => 'Firma registrada en la planilla.',
                'referencia_documento' => $f['documento_respuesta'],
            ]);
        }
        if (! $f['en_area'] && $f['fecha_egreso']) {
            $intervencion->movimientos()->create($base + [
                'tipo' => 'EGRESO',
                'fecha' => $f['fecha_egreso'],
                'estado_nuevo_id' => $f['estado']->es_final ? $f['estado']->id : null,
                'descripcion' => 'Egreso registrado en la planilla'.($destino ? ". Remitido a {$destino->nombre}" : '').'.',
                'referencia_documento' => $f['documento_respuesta'],
            ]);
        }
    }

    /**
     * Variantes de «Remitido a» agrupadas por clave normalizada; el nombre canónico es la variante más usada
     */
    private function agruparDestinos(array $registros): array
    {
        $grupos = [];
        foreach ($registros as $r) {
            if ($r['destino_texto'] === '') {
                continue;
            }
            $clave = $this->clave($r['destino_texto']);
            $grupos[$clave][$r['destino_texto']] = ($grupos[$clave][$r['destino_texto']] ?? 0) + 1;
        }
        $resultado = [];
        foreach ($grupos as $clave => $variantes) {
            arsort($variantes);
            $resultado[$clave] = ['canonico' => array_key_first($variantes), 'variantes' => $variantes];
        }
        $this->resumen['destinos_distintos_planilla'] = array_sum(array_map(fn ($g) => count($g['variantes']), $resultado));
        $this->resumen['destinos_tras_normalizar'] = count($resultado);

        return $resultado;
    }

    private function destino(array $destinos, string $texto): DependenciaDestino
    {
        $nombre = $destinos[$this->clave($texto)]['canonico'] ?? $texto;

        return DependenciaDestino::whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->first()
            ?? DependenciaDestino::create(['nombre' => $nombre]);
    }

    /**
     * Organismos (D-28): coinciden con ministerios existentes por nombre (con o sin « - SIGLA») o se crean
     */
    private function importarOrganismos(): array
    {
        $indice = [];
        foreach (Ministerio::all() as $m) {
            $indice[$this->clave($m->nombre)] = $m->id;
            if ($m->sigla) {
                $indice[$this->clave($m->sigla)] = $indice[$this->clave($m->sigla)] ?? $m->id;
            }
        }

        $archivo = $this->option('organismos');
        if (! $archivo) {
            return $indice;
        }

        $filas = $this->leerCsv($archivo) ?? [];
        $creados = $coincidentes = 0;
        foreach ($filas as $fila) {
            $nombre = trim((string) (reset($fila) ?: ''));
            if ($nombre === '') {
                continue;
            }
            [$sinSigla, $sigla] = str_contains($nombre, ' - ')
                ? array_map('trim', explode(' - ', $nombre, 2)) + [1 => null]
                : [$nombre, null];

            $id = $indice[$this->clave($nombre)] ?? $indice[$this->clave($sinSigla)] ?? ($sigla ? ($indice[$this->clave($sigla)] ?? null) : null);
            if ($id) {
                $indice[$this->clave($nombre)] = $id;
                $coincidentes++;

                continue;
            }

            $creados++;
            if ($this->dryRun) {
                $indice[$this->clave($nombre)] = -1; // Se crearía: cuenta como encontrado en la simulación

                continue;
            }
            $ministerio = Ministerio::create([
                'codigo' => $this->codigoOrganismo($sigla ?: $sinSigla),
                'nombre' => $nombre,
                'sigla' => $sigla,
                'orden_prioridad' => 100,
                'activo' => true,
            ]);
            $indice[$this->clave($nombre)] = $ministerio->id;
        }

        $this->resumen['organismos_coincidentes'] = $coincidentes;
        $this->resumen['organismos_creados'] = $creados;

        return $indice;
    }

    private function codigoOrganismo(string $base): string
    {
        $palabras = array_filter(preg_split('/\s+/', Str::ascii($base)), fn ($p) => mb_strlen($p) > 2 || ctype_upper($p));
        $codigo = mb_strtoupper(mb_strlen($base) <= 12 && ! str_contains($base, ' ')
            ? Str::ascii($base)
            : implode('', array_map(fn ($p) => mb_substr($p, 0, 1), $palabras)));
        $codigo = mb_substr(preg_replace('/[^A-Z0-9]/', '', $codigo) ?: 'ORG', 0, 16);

        $candidato = $codigo;
        for ($i = 2; Ministerio::withTrashed()->where('codigo', $candidato)->exists(); $i++) {
            $candidato = $codigo.$i;
        }

        return $candidato;
    }

    private function leerEquivalencias(): array
    {
        $mapa = [];
        $archivo = $this->option('equivalencias');
        if ($archivo) {
            foreach ($this->leerCsv($archivo) ?? [] as $n => $fila) {
                $valores = array_values($fila);
                $nombre = trim((string) ($valores[0] ?? ''));
                $email = trim((string) ($valores[1] ?? ''));
                if ($nombre === '' || $email === '') {
                    continue;
                }
                $user = User::whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();
                if ($user) {
                    $mapa[$this->clave($nombre)] = $user->id;
                } else {
                    $this->advertir(0, 'equivalencias', "No existe un usuario con el email {$email} (para «{$nombre}»)");
                }
            }
        }

        return ['mapa' => $mapa];
    }

    private function fecha(string $valor, int $fila, string $campo, array &$notas, ?Carbon $minimo = null): ?Carbon
    {
        if ($valor === '') {
            return null;
        }

        $limpio = preg_replace('#/{2,}#', '/', $valor);
        $fecha = null;
        $corregida = false;

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $limpio, $m)) {
            [$a, $mes, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{3,4})$#', $limpio, $m)) {
            [$d, $mes, $a] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            $this->advertir($fila, $campo, "Valor no reconocible como fecha («{$valor}»): queda vacío");
            $notas[] = "{$campo} (planilla): {$valor}";

            return null;
        }

        // Año mal tipeado (p. ej. 0206): se usa el año de la hoja y se deja constancia
        if ($a < 1900) {
            $a = $this->anioHoja;
            $corregida = true;
        }

        if (checkdate($mes, $d, $a)) {
            $fecha = Carbon::create($a, $mes, $d)->startOfDay();
        }

        if (! $fecha) {
            $this->advertir($fila, $campo, "Fecha inválida («{$valor}»): queda vacía");
            $notas[] = "{$campo} (planilla): {$valor}";

            return null;
        }

        if ($corregida || $limpio !== $valor) {
            $this->advertir($fila, $campo, "Fecha corregida: «{$valor}» → {$fecha->format('d/m/Y')}");
            $notas[] = "{$campo} corregida al importar (original: {$valor})";
        }

        if ($fecha->gt(now()->endOfDay())) {
            $this->advertir($fila, $campo, "Fecha futura («{$valor}»): queda vacía");
            $notas[] = "{$campo} (planilla, fecha futura): {$valor}";

            return null;
        }

        if ($minimo && $fecha->lt($minimo)) {
            $this->advertir($fila, $campo, "Fecha anterior al ingreso («{$valor}»): queda vacía");
            $notas[] = "{$campo} (planilla, anterior al ingreso): {$valor}";

            return null;
        }

        return $fecha;
    }

    private function leerCsv(string $ruta): ?array
    {
        $ruta = str_starts_with($ruta, '/') ? $ruta : base_path($ruta);
        if (! is_readable($ruta)) {
            $this->error("No se puede leer el archivo: {$ruta}");

            return null;
        }

        $handle = fopen($ruta, 'r');
        $primera = fgets($handle);
        $separador = substr_count($primera, ';') >= substr_count($primera, ',') ? ';' : ',';
        rewind($handle);

        $encabezados = null;
        $filas = [];
        while (($datos = fgetcsv($handle, 0, $separador)) !== false) {
            // Excel en Windows suele exportar en Windows-1252: se convierte a UTF-8
            $datos = array_map(fn ($c) => mb_check_encoding((string) $c, 'UTF-8') ? (string) $c : mb_convert_encoding((string) $c, 'UTF-8', 'Windows-1252'), $datos);
            if ($encabezados === null) {
                $datos[0] = preg_replace('/^\xEF\xBB\xBF/', '', $datos[0] ?? '');
                // Encabezados comparados sin acentos ni símbolos (Nº / N° / Nro.)
                $encabezados = array_map(fn ($h) => $this->clave((string) $h), $datos);

                continue;
            }
            if (count(array_filter($datos, fn ($c) => trim((string) $c) !== '')) === 0) {
                $filas[] = []; // Mantener la numeración de filas

                continue;
            }
            $filas[] = array_combine($encabezados, array_pad(array_slice($datos, 0, count($encabezados)), count($encabezados), ''));
        }
        fclose($handle);

        return array_filter($filas);
    }

    /**
     * Ubica cada columna lógica en el CSV: por nombre (sin acentos) o, si no, por una palabra clave
     */
    private function resolverColumnas(array $encabezados): void
    {
        $palabras = ['numero' => 'expediente', 'responsables' => 'analizado', 'revisor' => 'enviado', 'acto' => 'acto', 'destino' => 'remitido'];
        foreach (self::COLUMNAS as $col => $etiqueta) {
            $clave = $this->clave($etiqueta);
            if (in_array($clave, $encabezados, true)) {
                $this->columnas[$col] = $clave;

                continue;
            }
            foreach ($encabezados as $h) {
                if (isset($palabras[$col]) && str_contains($h, $palabras[$col])) {
                    $this->columnas[$col] = $h;

                    break;
                }
            }
        }
    }

    private function indice($coleccion, string $campo): array
    {
        return $coleccion->mapWithKeys(fn ($m) => [$this->clave($m->{$campo}) => $m])->all();
    }

    // Clave de comparación: sin acentos, minúsculas, solo letras y dígitos separados por un espacio
    private function clave(?string $texto): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii((string) $texto))));
    }

    private function advertir(int $fila, string $campo, string $mensaje, string $tipo = 'advertencia'): void
    {
        $this->informe[] = [$fila ?: '-', $tipo, $campo, $mensaje];
        $this->contar("informe:{$tipo}");
    }

    private function contar(string $clave, int $cantidad = 1): void
    {
        $this->resumen[$clave] = ($this->resumen[$clave] ?? 0) + $cantidad;
    }

    private function escribirInforme(array $destinos, array $sinEquivalencia): void
    {
        $dir = storage_path('app/importaciones/expedientes_'.now()->format('Ymd_His').($this->dryRun ? '_simulacion' : ''));
        @mkdir($dir, 0775, true);

        $csv = function (string $archivo, array $encabezado, array $filas) use ($dir) {
            $h = fopen("{$dir}/{$archivo}", 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, $encabezado, ';');
            foreach ($filas as $f) {
                fputcsv($h, $f, ';');
            }
            fclose($h);
        };

        $csv('informe.csv', ['Fila planilla', 'Tipo', 'Campo', 'Mensaje'], $this->informe);

        $filasDestinos = [];
        foreach ($destinos as $g) {
            foreach ($g['variantes'] as $variante => $cantidad) {
                $filasDestinos[] = [$variante, $g['canonico'], $cantidad];
            }
        }
        $csv('destinos.csv', ['Variante en la planilla', 'Nombre que se usará', 'Filas'], $filasDestinos);

        $csv('equivalencias_pendientes.csv', ['nombre', 'email'], array_map(fn ($n) => [$n, ''], array_keys($sinEquivalencia)));

        ksort($this->resumen);
        file_put_contents("{$dir}/resumen.txt", collect($this->resumen)->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n")."\n");

        $this->newLine();
        $this->table(['Concepto', 'Valor'], collect($this->resumen)->map(fn ($v, $k) => [$k, $v])->values()->all());
        if ($sinEquivalencia) {
            $this->warn(count($sinEquivalencia).' nombres de la planilla sin usuario equivalente (ver equivalencias_pendientes.csv).');
        }
        $this->info('Informe: '.$dir);
    }
}
