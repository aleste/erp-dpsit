<?php

namespace App\Services;

use App\Models\AsignacionRol;
use App\Models\ServicioReferente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Obtener lista filtrada de contactos y roles
     */
    public function getFilteredContacts(array $filters): Collection
    {
        $soloVigentes = $filters['solo_vigentes'] ?? true;
        $soloMaximasAutoridades = $filters['solo_maximas_autoridades'] ?? false;
        $ministerioIds = $filters['ministerio_ids'] ?? [];
        $areaTiIds = $filters['area_ti_ids'] ?? [];
        $tipoRolIds = $filters['tipo_rol_ids'] ?? [];
        $soloHomologadas = $filters['solo_homologadas'] ?? true;

        $query = AsignacionRol::with([
            'persona',
            'areaTi.ministerio',
            'tipoRol'
        ]);

        if ($soloVigentes) {
            $query->vigente();
        }

        if ($soloMaximasAutoridades) {
            $query->maximaAutoridad();
        }

        if (!empty($tipoRolIds)) {
            $query->whereIn('tipo_rol_id', (array)$tipoRolIds);
        }

        if (!empty($areaTiIds)) {
            $query->whereIn('area_ti_id', (array)$areaTiIds);
        }

        if (!empty($ministerioIds)) {
            $query->whereHas('areaTi', function (Builder $q) use ($ministerioIds) {
                $q->whereIn('ministerio_id', (array)$ministerioIds);
            });
        }

        if ($soloHomologadas) {
            $query->whereHas('areaTi', function (Builder $q) {
                $q->where('es_homologada', true);
            });
        }

        return $query->get()->map(function (AsignacionRol $asig) {
            $persona = $asig->persona;
            $area = $asig->areaTi;
            $ministerio = $area?->ministerio;
            $rol = $asig->tipoRol;

            return [
                'persona_id' => $persona->id,
                'cuil_cuit' => $persona->cuil_cuit,
                'apellido' => $persona->apellido,
                'nombre' => $persona->nombre,
                'nombre_completo' => $persona->nombre_completo,
                'email_principal' => $persona->email_principal,
                'email_alternativo' => $persona->email_alternativo,
                'telefono_principal' => $persona->telefono_principal,
                'telefono_alternativo' => $persona->telefono_alternativo,
                'ministerio' => $ministerio?->nombre,
                'ministerio_sigla' => $ministerio?->sigla,
                'area_ti' => $area?->nombre,
                'area_ti_codigo' => $area?->codigo,
                'es_homologada' => $area?->es_homologada,
                'rol' => $rol?->nombre,
                'rol_codigo' => $rol?->codigo,
                'es_maxima_autoridad' => $asig->es_maxima_autoridad,
                'fecha_inicio' => $asig->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $asig->fecha_fin?->format('Y-m-d'),
                'estado_vigencia' => $asig->estado_vigencia,
                'acto_administrativo' => $asig->acto_administrativo,
            ];
        });
    }

    /**
     * Genera un array plano con solo correos únicos para campañas de comunicación masiva
     */
    public function getUniqueEmails(array $filters): array
    {
        $incluirAlternativos = $filters['incluir_alternativos'] ?? false;
        $contactos = $this->getFilteredContacts($filters);

        $emails = [];
        foreach ($contactos as $c) {
            if (!empty($c['email_principal'])) {
                $emails[strtolower(trim($c['email_principal']))] = [
                    'email' => strtolower(trim($c['email_principal'])),
                    'nombre_completo' => $c['nombre_completo'],
                    'rol' => $c['rol'],
                    'ministerio' => $c['ministerio_sigla'] ?: $c['ministerio'],
                    'area_ti' => $c['area_ti'],
                    'tipo' => 'PRINCIPAL',
                ];
            }

            if ($incluirAlternativos && !empty($c['email_alternativo'])) {
                $alt = strtolower(trim($c['email_alternativo']));
                if (!isset($emails[$alt])) {
                    $emails[$alt] = [
                        'email' => $alt,
                        'nombre_completo' => $c['nombre_completo'],
                        'rol' => $c['rol'],
                        'ministerio' => $c['ministerio_sigla'] ?: $c['ministerio'],
                        'area_ti' => $c['area_ti'],
                        'tipo' => 'ALTERNATIVO',
                    ];
                }
            }
        }

        return array_values($emails);
    }

    /**
     * Descarga de contactos en formato CSV (RFC 4180 con UTF-8 BOM para Excel)
     */
    public function streamCsv(array $filters, string $filename = 'contactos_dpsit.csv'): StreamedResponse
    {
        $contactos = $this->getFilteredContacts($filters);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($contactos) {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM para apertura correcta en Microsoft Excel en español
            fputs($handle, "\xEF\xBB\xBF");

            // Cabeceras de columnas
            fputcsv($handle, [
                'CUIL/CUIT',
                'Apellido',
                'Nombre',
                'Email Principal',
                'Email Alternativo',
                'Teléfono Principal',
                'Teléfono Alternativo',
                'Ministerio',
                'Área TI',
                'Es Homologada',
                'Rol / Cargo',
                'Es Máxima Autoridad',
                'Estado Vigencia',
                'Fecha Inicio',
                'Fecha Fin',
                'Acto Designación'
            ], ';');

            foreach ($contactos as $c) {
                fputcsv($handle, [
                    $c['cuil_cuit'],
                    $c['apellido'],
                    $c['nombre'],
                    $c['email_principal'],
                    $c['email_alternativo'],
                    $c['telefono_principal'],
                    $c['telefono_alternativo'],
                    $c['ministerio'],
                    $c['area_ti'],
                    $c['es_homologada'] ? 'SI' : 'NO',
                    $c['rol'],
                    $c['es_maxima_autoridad'] ? 'SI' : 'NO',
                    $c['estado_vigencia'],
                    $c['fecha_inicio'],
                    $c['fecha_fin'] ?? 'VIGENTE',
                    $c['acto_administrativo'] ?? '-',
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
