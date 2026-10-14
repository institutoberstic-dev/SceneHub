<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Versión del paquete territorial del PT1. Publicada = inmutable; se retira, no se borra. */
class PaqueteTerritorial extends Model
{
    public const BORRADOR = 'borrador';

    public const VALIDADO = 'validado';

    public const PUBLICADO = 'publicado';

    public const RETIRADO = 'retirado';

    protected $table = 'paquete_territorial';

    protected $fillable = ['version', 'estado', 'notas', 'creado_por', 'publicado_en', 'publicado_por', 'retirado_en', 'retirado_por', 'motivo_retiro'];

    protected function casts(): array
    {
        return ['publicado_en' => 'datetime', 'retirado_en' => 'datetime'];
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(ArchivoTerritorial::class, 'paquete_id')->orderBy('tipo')->orderBy('id');
    }

    public function validaciones(): HasMany
    {
        return $this->hasMany(ValidacionTerritorial::class, 'paquete_id')->orderBy('id');
    }

    public function registros(): HasMany
    {
        return $this->hasMany(RegistroTerritorial::class, 'paquete_id')->orderByDesc('id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function publicador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    /** Solo un borrador o un paquete validado admite cambios de archivos. */
    public function esEditable(): bool
    {
        return in_array($this->estado, [self::BORRADOR, self::VALIDADO], true);
    }

    public function carpeta(): string
    {
        return config('territorio.carpeta').'/'.$this->id.'-'.$this->version;
    }
}
