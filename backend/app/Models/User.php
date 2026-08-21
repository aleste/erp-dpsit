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
