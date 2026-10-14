<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Quién hizo qué y cuándo en el módulo territorial. Solo se agregan filas. */
class RegistroTerritorial extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'registro_territorial';

    protected $fillable = ['paquete_id', 'user_id', 'accion', 'detalle'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
