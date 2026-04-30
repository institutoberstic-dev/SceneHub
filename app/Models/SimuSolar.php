<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimuSolar extends Model
{
    //
    protected $table = 'simu_solars';
    protected $primaryKey = 'id';
    protected $fillable = [
        'simulationRunning',
        'inclinacion',
        'direccion',
        'latitud',
        'inclinacionOptima',
        'estado',
        'radiacion',
        'perdidas',
        'generacion',
        'conexion',
        'temperatura',
        'voltaje',
        'corriente',
        'electrolizacion',
        'bateria',
        'consumo',
    ];
}
