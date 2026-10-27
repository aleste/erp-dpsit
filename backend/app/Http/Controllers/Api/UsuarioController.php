<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserModulo;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Administración de usuarios y permisos (D-27, D-34; solo ADMINISTRADOR). Los usuarios no se eliminan: se
 * desactivan. El ADMINISTRADOR accede a todos los módulos sin filas en user_modulos; el USUARIO, solo a los
 * que tenga asignados.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'perfil' => ['nullable', Rule::in(User::PERFILES)],
            'modulo' => ['nullable', Rule::in(array_keys(UserModulo::ROLES))],
        ]);

        $query = User::with(['modulos', 'persona'])->orderBy('name');

        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(fn ($q) => $q->where('name', 'ILIKE', "%{$term}%")->orWhere('email', 'ILIKE', "%{$term}%"));
        }

        if ($request->filled('activo')) {
            $query->where('activo', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('perfil')) {
            $query->where('perfil_acceso', $request->perfil);
        }

        // Con acceso al módulo: asignado explícitamente o administrador (accede a todos)
        if ($request->filled('modulo')) {
            $query->where(fn ($q) => $q->where('perfil_acceso', User::ADMINISTRADOR)
                ->orWhereHas('modulos', fn ($m) => $m->where('modulo', $request->modulo)));
        }

        $usuarios = $query->get();
        $cambios = $this->ultimosCambiosDePermisos($usuarios->pluck('id')->all());
        $usuarios->each(fn ($u) => $u->setAttribute('ultimo_cambio_permisos', $cambios[$u->id] ?? null));

        return response()->json([
            'success' => true,
            'data' => $usuarios,
            'meta' => [
                'perfiles' => User::PERFILES,
                'modulos' => UserModulo::ROLES,
                'catalogo_modulos' => UserModulo::catalogo(),
            ],
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

        AuditService::log('CREATE', 'USUARIOS', $user->id, null, $this->paraAuditoria($user->load('modulos')), 'Alta de usuario');

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado correctamente',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate($this->reglas($id) + [
            'password' => ['nullable', 'string', Password::min(8)],
        ]);

        $esPropio = $id === $request->user()->id;
        $quedaAdminActivo = $validated['perfil_acceso'] === User::ADMINISTRADOR && ($validated['activo'] ?? true);

        if ($esPropio && ! $quedaAdminActivo) {
            return response()->json([
                'success' => false,
                'message' => 'No puede desactivar su propio usuario ni quitarse el perfil de administrador.',
            ], 422);
        }

        $cambiaClave = ! empty($validated['password']);
        if (! $cambiaClave) {
            unset($validated['password']);
        }

        $resultado = DB::transaction(function () use ($id, $validated, $quedaAdminActivo) {
            $user = User::with('modulos')->lockForUpdate()->findOrFail($id);

            // Siempre debe quedar al menos un administrador activo. El bloqueo serializa dos degradaciones
            // simultáneas de administradores distintos.
            if ($user->esAdmin() && $user->activo && ! $quedaAdminActivo) {
                $otrosAdmins = User::where('perfil_acceso', User::ADMINISTRADOR)->where('activo', true)
                    ->where('id', '!=', $user->id)->lockForUpdate()->pluck('id'); // FOR UPDATE no admite count()
                if ($otrosAdmins->isEmpty()) {
                    return null;
                }
            }

            $anterior = $this->paraAuditoria($user);
            $user->update(collect($validated)->except('modulos')->all());
            if (array_key_exists('modulos', $validated) || $user->esAdmin()) {
                $this->sincronizarModulos($user, $validated['modulos'] ?? []);
            }
            // Un usuario desactivado pierde sus sesiones abiertas
            if (! $user->activo) {
                $user->tokens()->delete();
            }

            return [$user->fresh(['modulos', 'persona']), $anterior];
        });

        if ($resultado === null) {
            return response()->json([
                'success' => false,
                'message' => 'No es posible: el sistema debe conservar al menos un administrador activo.',
            ], 422);
        }

        [$user, $anterior] = $resultado;
        $this->auditarCambios($user, $anterior, $this->paraAuditoria($user), $cambiaClave);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente',
            'data' => $user,
        ]);
    }

    private function reglas(?int $id = null): array
    {
        $modulos = array_keys(UserModulo::ROLES);

        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'perfil_acceso' => ['required', Rule::in(User::PERFILES)],
            'activo' => 'boolean',
            'persona_id' => 'nullable|exists:personas,id',
            'modulos' => 'nullable|array',
            'modulos.*.modulo' => ['required', 'distinct', Rule::in($modulos)],
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
     * Reemplaza los roles por módulo del usuario (sin rol = sin acceso a ese módulo). El administrador no
     * guarda filas: accede a todos los módulos con el rol máximo.
     */
    private function sincronizarModulos(User $user, array $modulos): void
    {
        $user->modulos()->delete();
        if ($user->esAdmin()) {
            return;
        }
        foreach (collect($modulos)->unique('modulo') as $m) {
            $user->modulos()->create(['modulo' => $m['modulo'], 'rol' => $m['rol']]);
        }
    }

    /**
     * Separa en la bitácora los cambios de permisos (acción PERMISOS) de los de datos personales (UPDATE)
     */
    private function auditarCambios(User $user, array $anterior, array $nuevo, bool $cambiaClave): void
    {
        $camposPermisos = ['perfil_acceso', 'activo', 'modulos'];
        $permisosAntes = array_intersect_key($anterior, array_flip($camposPermisos));
        $permisosDespues = array_intersect_key($nuevo, array_flip($camposPermisos));

        if ($permisosAntes != $permisosDespues) {
            AuditService::log('PERMISOS', 'USUARIOS', $user->id, $permisosAntes, $permisosDespues, $this->describirCambioPermisos($permisosAntes, $permisosDespues));
        }

        $datosAntes = array_diff_key($anterior, array_flip($camposPermisos));
        $datosDespues = array_diff_key($nuevo, array_flip($camposPermisos));

        if ($datosAntes != $datosDespues || $cambiaClave) {
            AuditService::log('UPDATE', 'USUARIOS', $user->id, $datosAntes, $datosDespues, $cambiaClave ? 'Modificación de usuario (incluye cambio de contraseña)' : 'Modificación de usuario');
        }
    }

    private function describirCambioPermisos(array $antes, array $despues): string
    {
        $partes = [];
        if ($antes['perfil_acceso'] !== $despues['perfil_acceso']) {
            $partes[] = "Perfil: {$antes['perfil_acceso']} → {$despues['perfil_acceso']}";
        }
        if ($antes['activo'] !== $despues['activo']) {
            $partes[] = $despues['activo'] ? 'Usuario activado' : 'Usuario desactivado';
        }

        $rolesAntes = $this->rolesPorModulo($antes['modulos']);
        $rolesDespues = $this->rolesPorModulo($despues['modulos']);
        foreach (array_keys(UserModulo::ROLES) as $modulo) {
            $de = $rolesAntes[$modulo] ?? 'sin acceso';
            $a = $rolesDespues[$modulo] ?? 'sin acceso';
            if ($de !== $a) {
                $partes[] = "{$modulo}: {$de} → {$a}";
            }
        }

        return 'Cambio de permisos. '.implode('; ', $partes);
    }

    private function rolesPorModulo(array $modulos): array
    {
        return collect($modulos)->mapWithKeys(function ($m) {
            [$modulo, $rol] = explode(':', $m);

            return [$modulo => $rol];
        })->all();
    }

    /**
     * Fecha y autor del último cambio de permisos de cada usuario, desde la bitácora
     */
    private function ultimosCambiosDePermisos(array $ids): array
    {
        return AuditLog::with('user:id,name')
            ->where('modulo', 'USUARIOS')
            ->whereIn('accion', ['PERMISOS', 'CREATE'])
            ->whereIn('registro_id', $ids)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->unique('registro_id')
            ->mapWithKeys(fn ($log) => [$log->registro_id => [
                'fecha' => $log->created_at,
                'autor' => $log->user?->name,
            ]])
            ->all();
    }

    private function paraAuditoria(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'perfil_acceso' => $user->perfil_acceso,
            'activo' => (bool) $user->activo,
            'modulos' => $user->modulos->map(fn ($m) => "{$m->modulo}:{$m->rol}")->sort()->values()->all(),
        ];
    }
}
