<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permiso por módulo (D-26, D-34). Uso en rutas: middleware('modulo:EXPEDIENTES,OPERADOR').
 * Varios módulos alternativos con «|»: middleware('modulo:PADRON|FIRMA_DIGITAL,OPERADOR').
 * El ADMINISTRADOR global siempre tiene el rol máximo de cada módulo.
 */
class CheckModuloAcceso
{
    public function handle(Request $request, Closure $next, string $modulos, string $rolMinimo): Response
    {
        $user = $request->user();

        if (! $user || ! $user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado o usuario inactivo.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->puedeEnModulo($modulos, $rolMinimo)) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado: su usuario no tiene el rol requerido en este módulo.',
                'modulo' => $modulos,
                'rol_requerido' => $rolMinimo,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
