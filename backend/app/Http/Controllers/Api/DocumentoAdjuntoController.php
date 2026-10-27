<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentoAdjunto;
use App\Models\UserModulo;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentoAdjuntoController extends Controller
{
    /** Módulo que gobierna los adjuntos de cada tipo de entidad (D-34) */
    private const MODULO_POR_TIPO = [
        'area_ti' => UserModulo::ORGANISMOS,
        'asignacion_rol' => UserModulo::ORGANISMOS,
        'servicio_area' => UserModulo::SERVICIOS,
        'persona' => UserModulo::PADRON,
    ];

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documentable_type' => 'required|string|in:area_ti,servicio_area,asignacion_rol,persona',
            'documentable_id' => 'required|integer',
            'tipo_documento' => 'required|string|max:50',
            'titulo' => 'required|string|max:255',
            'archivo' => 'required|file|max:20480', // Máx 20MB
            'observaciones' => 'nullable|string',
        ]);

        if ($denegado = $this->sinPermiso($request, $validated['documentable_type'], 'OPERADOR')) {
            return $denegado;
        }

        $file = $request->file('archivo');
        $sha256 = hash_file('sha256', $file->getRealPath());

        // Guardar archivo en disco privado
        $path = $file->store('documents/'.$validated['documentable_type'], 'local');

        $documento = DocumentoAdjunto::create([
            'documentable_type' => $validated['documentable_type'],
            'documentable_id' => $validated['documentable_id'],
            'tipo_documento' => $validated['tipo_documento'],
            'titulo' => $validated['titulo'],
            'archivo_nombre_original' => $file->getClientOriginalName(),
            'archivo_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'tamanio_bytes' => $file->getSize(),
            'sha256' => $sha256,
            'observaciones' => $validated['observaciones'] ?? null,
            'subido_por_user_id' => Auth::id(),
        ]);

        AuditService::log('UPLOAD', 'DOCUMENTOS', $documento->id, null, [
            'titulo' => $documento->titulo,
            'tipo' => $documento->tipo_documento,
            'sha256' => $sha256,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Documento digital adjuntado exitosamente',
            'data' => $documento,
        ], 201);
    }

    public function download(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $documento = DocumentoAdjunto::findOrFail($id);

        if ($denegado = $this->sinPermiso($request, $documento->getRawOriginal('documentable_type'), 'CONSULTA')) {
            return $denegado;
        }

        if (! Storage::disk('local')->exists($documento->archivo_path)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo físico no se encuentra en el almacenamiento.',
            ], 404);
        }

        AuditService::log('DOWNLOAD', 'DOCUMENTOS', $documento->id, null, null, "Descarga de {$documento->archivo_nombre_original}");

        $fullPath = Storage::disk('local')->path($documento->archivo_path);

        return response()->download($fullPath, $documento->archivo_nombre_original, [
            'Content-Type' => $documento->mime_type,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $documento = DocumentoAdjunto::findOrFail($id);

        // Soft delete del registro
        $documento->delete();

        AuditService::log('DELETE', 'DOCUMENTOS', $id, ['titulo' => $documento->titulo], null);

        return response()->json([
            'success' => true,
            'message' => 'Documento adjunto eliminado correctamente',
        ]);
    }

    private function sinPermiso(Request $request, string $tipo, string $rolMinimo): ?JsonResponse
    {
        $modulo = self::MODULO_POR_TIPO[$tipo] ?? null;

        if ($modulo && $request->user()->puedeEnModulo($modulo, $rolMinimo)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Acceso denegado: su usuario no tiene el rol requerido en este módulo.',
            'modulo' => $modulo,
            'rol_requerido' => $rolMinimo,
        ], 403);
    }
}
