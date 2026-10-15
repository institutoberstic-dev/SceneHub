<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Versión publicada de la app de escritorio «Puente de datos». Se retira, no se borra. */
class PuenteVersion extends Model
{
    protected $table = 'puente_versiones';

    protected $fillable = ['version', 'tipo', 'notas', 'nombre_original', 'ruta', 'tamano', 'sha256', 'subido_por', 'retirada_en', 'retirada_por', 'motivo_retiro'];

    protected $hidden = ['ruta'];

    protected function casts(): array
    {
        return ['retirada_en' => 'datetime', 'tamano' => 'integer', 'descargas' => 'integer'];
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function retiradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retirada_por');
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('retirada_en');
    }

    public function vigente(): bool
    {
        return $this->retirada_en === null;
    }

    /** Nombre con el que se descarga: PuenteDatos-1.2.0-instalador.exe */
    public function nombreDescarga(): string
    {
        $extension = strtolower(pathinfo($this->nombre_original, PATHINFO_EXTENSION));

        return "PuenteDatos-{$this->version}-{$this->tipo}.{$extension}";
    }
}
