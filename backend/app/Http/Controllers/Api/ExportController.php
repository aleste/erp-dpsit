<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService
    ) {}

    /**
     * Vista previa y conteo de contactos según los filtros configurados
     */
    public function preview(Request $request): JsonResponse
    {
        $filters = $request->only([
            'ministerio_ids',
            'area_ti_ids',
            'tipo_rol_ids',
            'solo_vigentes',
            'solo_maximas_autoridades',
            'solo_homologadas',
            'incluir_alternativos',
        ]);

        $contactos = $this->exportService->getFilteredContacts($filters);
        $uniqueEmails = $this->exportService->getUniqueEmails($filters);

        return response()->json([
            'success' => true,
            'total_registros' => $contactos->count(),
            'total_emails_unicos' => count($uniqueEmails),
            'data' => $contactos->take(50), // Muestra previa de hasta 50 registros
            'emails_unicos' => $uniqueEmails,
        ]);
    }

    /**
     * Descarga de listado en formato CSV
     */
    public function downloadCsv(Request $request): StreamedResponse
    {
        $filters = $request->only([
            'ministerio_ids',
            'area_ti_ids',
            'tipo_rol_ids',
            'solo_vigentes',
            'solo_maximas_autoridades',
            'solo_homologadas',
        ]);

        // Manejar listas separadas por coma desde query params
        foreach (['ministerio_ids', 'area_ti_ids', 'tipo_rol_ids'] as $key) {
            if ($request->filled($key) && is_string($request->$key)) {
                $filters[$key] = explode(',', $request->$key);
            }
        }

        AuditService::log('EXPORT_EMAILS', 'EXPORT', null, null, $filters, 'Exportación de contactos a CSV');

        $timestamp = now()->format('Ymd_His');
        $filename = "contactos_autoridades_dpsit_{$timestamp}.csv";

        return $this->exportService->streamCsv($filters, $filename);
    }
}
