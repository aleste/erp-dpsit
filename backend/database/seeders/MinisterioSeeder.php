<?php

namespace Database\Seeders;

use App\Models\Ministerio;
use App\Models\Persona;
use App\Services\AutoridadOrganismoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Organismos del Poder Ejecutivo y su máxima autoridad vigente (D-32). Son datos reales y públicos,
 * versionados por decisión del responsable. Idempotente: se puede ejecutar sobre una base con datos
 * (`php artisan db:seed --class=MinisterioSeeder`). En un cambio de gestión basta con actualizar la
 * lista: la autoridad anterior se cierra y queda en el historial.
 */
class MinisterioSeeder extends Seeder
{
    /** [codigo, nombre, apellido de la autoridad, nombre de la autoridad, sigla (si difiere del código)] */
    private const ORGANISMOS = [
        ['MAMB', 'Ministerio de Ambiente', 'Vila', 'Daniela Marina'],
        ['MCP', 'Ministerio de Comunicación Pública', 'Rey', 'Jesica Laura'],
        ['MDA', 'Ministerio de Desarrollo Agrario', 'Rodríguez', 'Javier Leonel'],
        ['MDC', 'Ministerio de Desarrollo de la Comunidad', 'Larroque', 'Andrés'],
        ['MECO', 'Ministerio de Economía', 'López', 'Pablo Julio'],
        ['MGOB', 'Ministerio de Gobierno', 'Bianco', 'Carlos Alberto'],
        ['MHDU', 'Ministerio de Hábitat y Desarrollo Urbano', 'Batakis', 'Silvina Aída'],
        ['MISP', 'Ministerio de Infraestructura y Servicios Públicos', 'Katopodis', 'Gabriel Nicolás'],
        ['MJDH', 'Ministerio de Justicia y Derechos Humanos', 'Mena', 'Juan Martín'],
        ['MMD', 'Ministerio de Mujeres y Diversidad', 'Díaz', 'Estela Elvira'],
        ['MPCEIT', 'Ministerio de Producción, Ciencia e Innovación Tecnológica', 'Costa', 'Augusto Eduardo'],
        ['MSAL', 'Ministerio de Salud', 'Kreplak', 'Nicolás'],
        ['MSEG', 'Ministerio de Seguridad', 'Alonso', 'Eduardo Javier'],
        ['MTRAB', 'Ministerio de Trabajo', 'Correa', 'Jorge Walter'],
        ['MTRANS', 'Ministerio de Transporte', 'Marinucc', 'Martin Fabio'],
        ['SG', 'Secretaría General', 'Vila', 'María Agustina'],
        ['DGCYE', 'Dirección General de Cultura y Educación', 'Terigi', 'Flavia Zulema'],
        ['JAG', 'Jefatura de Asesores del Gobernador', 'Álvarez', 'María Cristina'],
        ['TGP', 'Tesorería General de la Provincia', 'Jacoby', 'David René'],
        ['JE', 'Junta Electoral', 'Kogan', 'Hilda'],
        ['HTC', 'Honorable Tribunal de Cuentas', 'Thea', 'Federico Gastón'],
        ['FDE', 'Fiscalía de Estado', 'Gómez', 'Hernán Rodolfo'],
        ['CGP', 'Contaduría General de la Provincia', 'Baleztena', 'Carlos Francisco'],
        ['OPE', 'Observatorio Político Electoral', 'García', 'María Teresa'],
        ['UG', 'Unidad de Gobernación', 'Beltrám', 'Nicolás Ariel'],
    ];

    /**
     * Código vigente => código que usaba antes (ministerios demo o cambios de código). Solo sirve para
     * encontrar el registro viejo y renombrarlo, sin duplicarlo. El código final es siempre el de
     * ORGANISMOS, que coincide con la sigla.
     */
    private const CODIGOS_ANTERIORES = [
        'MSAL' => 'MS',
        'DGCYE' => 'ME',
        'MECO' => 'MEYF',
        'MSEG' => 'MYS',
        'MGOB' => 'MGG',
        'FDE' => 'FE',
    ];

    public function run(): void
    {
        $service = app(AutoridadOrganismoService::class);
        $personas = Persona::all(['id', 'apellido', 'nombre'])
            ->keyBy(fn ($p) => $this->clave($p->apellido.'|'.$p->nombre));

        foreach (self::ORGANISMOS as $orden => $organismo) {
            [$codigo, $nombre, $apellidoAutoridad, $nombreAutoridad] = $organismo;
            $sigla = $organismo[4] ?? $codigo;
            $ministerio = $this->buscarOrganismo($codigo, $nombre) ?? new Ministerio;
            if ($ministerio->trashed()) {
                $ministerio->restore();
            }
            $ministerio->fill([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'sigla' => $sigla,
                'orden_prioridad' => $orden + 1,
                'activo' => true,
            ])->save();

            $clavePersona = $this->clave($apellidoAutoridad.'|'.$nombreAutoridad);
            $persona = $personas[$clavePersona] ??= Persona::create([
                'apellido' => $apellidoAutoridad,
                'nombre' => $nombreAutoridad,
                'activo' => true,
            ]);

            if ($ministerio->autoridadVigente()->value('persona_id') === $persona->id) {
                continue;
            }

            try {
                $service->designar($ministerio, ['persona_id' => $persona->id]);
            } catch (ValidationException $e) {
                $this->command?->warn("{$nombre}: ".collect($e->errors())->flatten()->implode(' '));
            }
        }
    }

    private function buscarOrganismo(string $codigo, string $nombre): ?Ministerio
    {
        $ministerio = Ministerio::withTrashed()->where('codigo', $codigo)->first();
        if (! $ministerio && isset(self::CODIGOS_ANTERIORES[$codigo])) {
            $ministerio = Ministerio::withTrashed()->where('codigo', self::CODIGOS_ANTERIORES[$codigo])->first();
        }

        // Organismos creados por el importador de expedientes (D-28): «Nombre» o «Nombre - SIGLA»
        return $ministerio ?? Ministerio::withTrashed()->get()
            ->first(fn ($m) => $this->clave(Str::before($m->nombre, ' - ')) === $this->clave($nombre));
    }

    private function clave(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->squish()->value();
    }
}
