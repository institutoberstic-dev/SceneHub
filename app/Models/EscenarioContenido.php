<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscenarioContenido extends Model
{
    protected $table = 'escenario_contenidos';

    protected $fillable = [
        'escenario_id',
        'uploaded_by',
        'modified_by',
        'nombre',
        'nombre_original',
        'ruta',
        'tipo',
        'mime_type',
        'tamano',
        'fecha_archivo',
        'version',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'tamano' => 'integer',
            'fecha_archivo' => 'datetime',
            'version' => 'decimal:1',
        ];
    }

    public function escenario(): BelongsTo
    {
        return $this->belongsTo(Escenario::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}
