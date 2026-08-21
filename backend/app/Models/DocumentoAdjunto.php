<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class DocumentoAdjunto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'documentos_adjuntos';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'tipo_documento',
        'titulo',
        'archivo_nombre_original',
        'archivo_path',
        'mime_type',
        'tamanio_bytes',
        'sha256',
        'observaciones',
        'subido_por_user_id',
    ];

    protected $casts = [
        'tamanio_bytes' => 'integer',
    ];

    protected $appends = ['tamanio_formateado'];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }

    public function getTamanioFormateadoAttribute(): string
    {
        $bytes = $this->tamanio_bytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
