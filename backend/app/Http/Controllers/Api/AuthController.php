<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (!$user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario se encuentra desactivado. Comuníquese con el Administrador.',
            ], 403);
        }

        // Crear token con Sanctum
        $token = $user->createToken('erp-dpsit-token', [$user->perfil_acceso])->plainTextToken;

        AuditService::log('LOGIN', 'AUTH', $user->id, null, null, "Inicio de sesión exitoso ({$user->perfil_acceso})", $request);

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'perfil_acceso' => $user->perfil_acceso,
                'persona_id' => $user->persona_id,
                'modulos' => $user->modulosAcceso(),
            ],
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load('persona');
        return response()->json([
            'success' => true,
            'user' => array_merge($user->toArray(), ['modulos' => $user->modulosAcceso()]),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            AuditService::log('LOGOUT', 'AUTH', $user->id, null, null, "Cierre de sesión", $request);
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión finalizada correctamente',
        ]);
    }
}
