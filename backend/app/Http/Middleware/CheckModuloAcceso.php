<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permiso por módulo (D-26). Uso en rutas: middleware('modulo:EXPEDIENTES,OPERADOR').
 * El ADMINISTRADOR global siempre tiene el rol máximo del módulo.
 */
class CheckModuloAcceso
{
    public function handle(Request $request, Closure $next, string $modulo, string $rolMinimo): Response
    {
        $user = $request->user();

        if (! $user || ! $user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado o usuario inactivo.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->puedeEnModulo($modulo, $rolMinimo)) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado: su usuario no tiene el rol requerido en este módulo.',
                'modulo' => $modulo,
                'rol_actual' => $user->rolEnModulo($modulo),
                'rol_requerido' => $rolMinimo,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
