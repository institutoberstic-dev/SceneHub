<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo controlado de tecnologías participantes (RF tecnologías).
 *
 * El `codigo` es el identificador de interoperabilidad con el gemelo digital
 * en Unity: no se edita ni se reutiliza. Una tecnología que deja de usarse se
 * desactiva (`activo = false`) en lugar de eliminarse.
 */
class Tecnologia extends Model
{
    protected $table = 'tecnologias';

    protected $fillable = [
        'codigo',
        'nombre',
        'categoria_id',
        'activo',
        'orden',
    ];

    // La API expone cada tecnología como {codigo, nombre} y, si se carga, su categoria {codigo, nombre}.
    protected $hidden = [
        'id',
        'categoria_id',
        'activo',
        'orden',
        'pivot',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaTecnologia::class, 'categoria_id');
    }

    public function escenarios(): BelongsToMany
    {
        return $this->belongsToMany(Escenario::class, 'escenario_tecnologia', 'tecnologia_id', 'escenario_id')
            ->withTimestamps();
    }
}
