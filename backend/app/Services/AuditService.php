<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public static function log(
        string $accion,
        string $modulo,
        ?int $registroId = null,
        ?array $valoresAnteriores = null,
        ?array $valoresNuevos = null,
        ?string $detalles = null,
        ?Request $request = null
    ): AuditLog {
        $req = $request ?? request();

        return AuditLog::create([
            'user_id' => Auth::id(),
            'ip_address' => $req ? $req->ip() : null,
            'user_agent' => $req ? substr($req->userAgent() ?? '', 0, 500) : null,
            'accion' => strtoupper($accion),
            'modulo' => strtoupper($modulo),
            'registro_id' => $registroId,
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos' => $valoresNuevos,
            'detalles' => $detalles,
            'created_at' => now(),
        ]);
    }
}
