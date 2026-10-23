<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserModulo;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Administración de usuarios (D-27, solo ADMINISTRADOR). Los usuarios no se eliminan: se desactivan.
 */
class UsuarioController extends Controller
{
    public const PERFILES = ['ADMINISTRADOR', 'EDITOR', 'LECTOR'];

    public function index(Request $request): JsonResponse
    {
        $query = User::with(['modulos', 'persona'])->orderBy('name');

        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(fn ($q) => $q->where('name', 'ILIKE', "%{$term}%")->orWhere('email', 'ILIKE', "%{$term}%"));
        }

        if ($request->filled('activo')) {
            $query->where('activo', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
            'meta' => ['perfiles' => self::PERFILES, 'modulos' => UserModulo::ROLES],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reglas() + [
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create(collect($validated)->except('modulos')->all());
            $this->sincronizarModulos($user, $validated['modulos'] ?? []);

            return $user;
        });

        AuditService::log('CREATE', 'USUARIOS', $user->id, null, $this->paraAuditoria($user), 'Alta de usuario');

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado correctamente',
            'data' => $user->load('modulos'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::with('modulos')->findOrFail($id);
        $anterior = $this->paraAuditoria($user);

        $validated = $request->validate($this->reglas($id) + [
            'password' => ['nullable', 'string', Password::min(8)],
        ]);

        // Evita que un administrador se quite a sí mismo el acceso (quedaría el sistema sin administrador)
        if ($user->id === $request->user()->id
            && (($validated['activo'] ?? true) === false || ($validated['perfil_acceso'] ?? 'ADMINISTRADOR') !== 'ADMINISTRADOR')) {
            return response()->json([
                'success' => false,
                'message' => 'No puede desactivar su propio usuario ni quitarse el perfil de administrador.',
            ], 422);
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        DB::transaction(function () use ($user, $validated) {
            $user->update(collect($validated)->except('modulos')->all());
            if (array_key_exists('modulos', $validated)) {
                $this->sincronizarModulos($user, $validated['modulos'] ?? []);
            }
            // Un usuario desactivado pierde sus sesiones abiertas
            if (($validated['activo'] ?? true) === false) {
                $user->tokens()->delete();
            }
        });

        AuditService::log('UPDATE', 'USUARIOS', $user->id, $anterior, $this->paraAuditoria($user->fresh('modulos')), 'Modificación de usuario');

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente',
            'data' => $user->fresh(['modulos', 'persona']),
        ]);
    }

    private function reglas(?int $id = null): array
    {
        $modulos = array_keys(UserModulo::ROLES);

        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'perfil_acceso' => ['required', Rule::in(self::PERFILES)],
            'activo' => 'boolean',
            'persona_id' => 'nullable|exists:personas,id',
            'modulos' => 'nullable|array',
            'modulos.*.modulo' => ['required', Rule::in($modulos)],
            'modulos.*.rol' => ['required', function ($attribute, $value, $fail) use ($modulos) {
                $indice = explode('.', $attribute)[1];
                $modulo = request()->input("modulos.{$indice}.modulo");
                if (! in_array($modulo, $modulos, true) || ! in_array($value, UserModulo::ROLES[$modulo], true)) {
                    $fail('El rol indicado no existe para ese módulo.');
                }
            }],
        ];
    }

    /**
     * Reemplaza los roles por módulo del usuario (sin rol = sin acceso a ese módulo)
     */
    private function sincronizarModulos(User $user, array $modulos): void
    {
        $user->modulos()->delete();
        foreach (collect($modulos)->unique('modulo') as $m) {
            $user->modulos()->create(['modulo' => $m['modulo'], 'rol' => $m['rol']]);
        }
    }

    private function paraAuditoria(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'perfil_acceso' => $user->perfil_acceso,
            'activo' => $user->activo,
            'modulos' => $user->modulos->map(fn ($m) => "{$m->modulo}:{$m->rol}")->all(),
        ];
    }
}
