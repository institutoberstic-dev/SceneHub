<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Features extends Model
{
    protected $table = 'features';
    protected $primaryKey = 'id';
    protected $fillable = [
        'corriente',
        'temperatura',
        'presion',
        'eficiencia',
        'voltajeTotal',
        'produccionHidrogeno',
        'temperaturaAmbiente',
        'coefConvectivo',
        'resistenciaInterna',
        'numCeldas'
    ];
}
