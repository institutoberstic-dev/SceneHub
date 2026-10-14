<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidacionTerritorial extends Model
{
    public const OK = 'ok';

    public const ADVERTENCIA = 'advertencia';

    public const ERROR = 'error';

    protected $table = 'validacion_territorial';

    protected $fillable = ['paquete_id', 'archivo_id', 'regla', 'resultado', 'detalle', 'requiere_confirmacion', 'confirmada_en', 'confirmada_por'];

    protected function casts(): array
    {
        return ['requiere_confirmacion' => 'boolean', 'confirmada_en' => 'datetime'];
    }

    public function paquete(): BelongsTo
    {
        return $this->belongsTo(PaqueteTerritorial::class, 'paquete_id');
    }

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(ArchivoTerritorial::class, 'archivo_id');
    }

    public function confirmadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmada_por');
    }

    /** Una advertencia sin confirmar o un error impiden publicar. */
    public function bloquea(): bool
    {
        return $this->resultado === self::ERROR
            || ($this->resultado === self::ADVERTENCIA && $this->requiere_confirmacion && $this->confirmada_en === null);
    }
}
