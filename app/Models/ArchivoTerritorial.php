<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivoTerritorial extends Model
{
    protected $table = 'archivo_territorial';

    protected $fillable = [
        'paquete_id', 'tipo', 'descripcion', 'capa', 'nombre_original', 'nombre', 'ruta', 'tamano', 'mime_type',
        'sha256', 'sha256_declarado', 'crs', 'fuente', 'licencia', 'fecha_captura', 'es_sensible', 'subido_por',
    ];

    // La ruta interna nunca sale en las respuestas JSON.
    protected $hidden = ['ruta'];

    protected function casts(): array
    {
        return ['tamano' => 'integer', 'es_sensible' => 'boolean', 'fecha_captura' => 'date:Y-m-d'];
    }

    public function paquete(): BelongsTo
    {
        return $this->belongsTo(PaqueteTerritorial::class, 'paquete_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
