<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Escenario extends Model
{
    //
    protected $table = 'esceanarios';
    protected $primaryKey = 'id';
    protected $fillable = [
        'nombre',
        'estado',
        'versiones',
    ];

    public $timestamps = true;

}
