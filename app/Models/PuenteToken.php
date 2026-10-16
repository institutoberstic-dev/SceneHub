<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Token para la API. Solo se guarda su SHA-256.
 * Cada token pertenece a una cobertura (config/api_tokens.php): puente, territorio, simulacion,
 * emociones, capturas o unity (la app Unity: simulación, emociones y capturas).
 */
class PuenteToken extends Model
{
    public const PREFIJO = 'puente_';

    /** Valor que guardaban los primeros tokens de la app (equivale a la cobertura «puente»). */
    private const ALCANCE_HISTORICO = 'lectura';

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

    /** @return list<string> Coberturas definidas en config/api_tokens.php. */
    public static function coberturas(): array
    {
        return array_keys(config('api_tokens.coberturas', []));
    }

    /**
     * Crea un token nuevo y devuelve [modelo, token en claro]. El token en claro no se guarda.
     *
     * @throws InvalidArgumentException si la cobertura no existe.
     */
    public static function emitir(string $nombre, ?int $dias = null, string $cobertura = 'puente'): array
    {
        if (! in_array($cobertura, self::coberturas(), true)) {
            throw new InvalidArgumentException("Cobertura desconocida: {$cobertura}");
        }
        $claro = self::PREFIJO.bin2hex(random_bytes(32));
        $token = self::create([
            'nombre' => $nombre,
            'token_hash' => self::hash($claro),
            'alcance' => $cobertura,
            'expira_en' => $dias ? now()->addDays($dias) : null,
        ]);

        return [$token, $claro];
    }

    /** Cobertura del token; los tokens históricos («lectura») son de la cobertura «puente». */
    public function cobertura(): string
    {
        return $this->alcance === self::ALCANCE_HISTORICO ? 'puente' : (string) $this->alcance;
    }

    public function cubre(string $cobertura): bool
    {
        return $this->cobertura() === $cobertura;
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('revocado_en')
            ->where(fn (Builder $q) => $q->whereNull('expira_en')->orWhere('expira_en', '>', now()));
    }
}
