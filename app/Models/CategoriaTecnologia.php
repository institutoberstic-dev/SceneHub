<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Agrupa las tecnologías del catálogo. La API la expone como {codigo, nombre}. */
class CategoriaTecnologia extends Model
{
    protected $table = 'categorias_tecnologia';

    protected $fillable = [
        'codigo',
        'nombre',
        'orden',
    ];

    protected $hidden = [
        'id',
        'orden',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function tecnologias(): HasMany
    {
        return $this->hasMany(Tecnologia::class, 'categoria_id');
    }
}
