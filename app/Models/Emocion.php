<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Emocion extends Model
{
    protected $table = 'emociones';

    protected $fillable = [
        'nombre',
        'descripcion',
        'icono',
        'color',
        'intensidad',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'intensidad' => 'integer',
            'estado' => 'boolean',
        ];
    }
}
