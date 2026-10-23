<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'persona_id',
        'name',
        'email',
        'password',
        'perfil_acceso',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'activo' => 'boolean',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function modulos(): HasMany
    {
        return $this->hasMany(UserModulo::class, 'user_id');
    }

    /**
     * Rol del usuario en un módulo (null si no tiene acceso). El ADMINISTRADOR global tiene el rol máximo.
     */
    public function rolEnModulo(string $modulo): ?string
    {
        if ($this->esAdmin()) {
            return last(UserModulo::ROLES[$modulo] ?? [null]);
        }

        return $this->modulos->firstWhere('modulo', $modulo)?->rol;
    }

    /**
     * Mapa módulo => rol efectivo (solo los módulos a los que tiene acceso), para la interfaz
     */
    public function modulosAcceso(): array
    {
        return collect(array_keys(UserModulo::ROLES))
            ->mapWithKeys(fn ($m) => [$m => $this->rolEnModulo($m)])
            ->filter()
            ->all();
    }

    public function puedeEnModulo(string $modulo, string $rolMinimo): bool
    {
        return $this->activo && UserModulo::alcanza($modulo, $this->rolEnModulo($modulo), $rolMinimo);
    }

    public function esAdmin(): bool
    {
        return $this->perfil_acceso === 'ADMINISTRADOR';
    }

    public function esEditor(): bool
    {
        return in_array($this->perfil_acceso, ['ADMINISTRADOR', 'EDITOR']);
    }

    public function esLector(): bool
    {
        return in_array($this->perfil_acceso, ['ADMINISTRADOR', 'EDITOR', 'LECTOR']);
    }
}
