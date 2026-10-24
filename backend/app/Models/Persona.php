<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Persona extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'personas';

    public const TIPOS_DOCUMENTO = ['DNI', 'PASAPORTE', 'CI', 'LC', 'LE', 'OTRO'];

    protected $fillable = [
        'cuil_cuit',
        'tipo_documento',
        'numero_documento',
        'apellido',
        'nombre',
        'email_principal',
        'email_alternativo',
        'telefono_principal',
        'telefono_alternativo',
        'titulo_profesional',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $appends = ['nombre_completo'];

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->apellido}, {$this->nombre}";
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionRol::class, 'persona_id');
    }

    public function asignacionesVigentes(): HasMany
    {
        return $this->hasMany(AsignacionRol::class, 'persona_id')
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', now()->toDateString());
            });
    }

    public function serviciosComoReferente(): HasMany
    {
        return $this->hasMany(ServicioReferente::class, 'persona_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'persona_id');
    }

    public function entregasToken(): HasMany
    {
        return $this->hasMany(EntregaToken::class, 'persona_id');
    }

    public function autoridadesOrganismo(): HasMany
    {
        return $this->hasMany(AutoridadOrganismo::class, 'persona_id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(DocumentoAdjunto::class, 'documentable');
    }
}
