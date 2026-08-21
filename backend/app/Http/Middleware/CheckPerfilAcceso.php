<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPerfilAcceso
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$perfilesPermitidos
     */
    public function handle(Request $request, Closure $next, ...$perfilesPermitidos): Response
    {
        $user = $request->user();

        if (!$user || !$user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado o usuario inactivo.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Si el usuario es ADMINISTRADOR, siempre tiene acceso
        if ($user->perfil_acceso === 'ADMINISTRADOR') {
            return $next($request);
        }

        // Si no se pasaron perfiles, permitir si está activo
        if (empty($perfilesPermitidos)) {
            return $next($request);
        }

        // Verificar si el perfil del usuario está en la lista de permitidos
        if (!in_array($user->perfil_acceso, $perfilesPermitidos)) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado: Su perfil de usuario no posee los privilegios requeridos.',
                'perfil_actual' => $user->perfil_acceso,
                'perfiles_requeridos' => $perfilesPermitidos,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
