<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Escenario extends Model
{
    protected $table = 'esceanarios';
    protected $primaryKey = 'id';
    protected $fillable = [
        'nombre',
        'owner_id',
        'estado',
        'descripcion',
        'versiones',
    ];

    public $timestamps = true;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'escenarios_users')
            ->withPivot(['access_level', 'invited_by'])
            ->withTimestamps();
    }

    public function contenidos(): HasMany
    {
        return $this->hasMany(EscenarioContenido::class)->latest();
    }
}
