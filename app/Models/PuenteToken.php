<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Token de solo lectura para la app «Puente de datos». Solo se guarda su SHA-256. */
class PuenteToken extends Model
{
    public const PREFIJO = 'puente_';

    protected $table = 'puente_tokens';

    protected $fillable = ['nombre', 'token_hash', 'alcance', 'expira_en', 'ultimo_uso_en', 'revocado_en'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expira_en' => 'datetime', 'ultimo_uso_en' => 'datetime', 'revocado_en' => 'datetime'];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Crea un token nuevo y devuelve [modelo, token en claro]. El token en claro no se guarda. */
    public static function emitir(string $nombre, ?int $dias = null): array
    {
        $claro = self::PREFIJO.bin2hex(random_bytes(32));
        $token = self::create([
            'nombre' => $nombre,
            'token_hash' => self::hash($claro),
            'alcance' => 'lectura',
            'expira_en' => $dias ? now()->addDays($dias) : null,
        ]);

        return [$token, $claro];
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('revocado_en')
            ->where(fn (Builder $q) => $q->whereNull('expira_en')->orWhere('expira_en', '>', now()));
    }
}
