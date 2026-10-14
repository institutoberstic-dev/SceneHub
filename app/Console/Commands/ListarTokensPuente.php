<?php

namespace App\Console\Commands;

use App\Models\PuenteToken;
use Illuminate\Console\Command;

class ListarTokensPuente extends Command
{
    protected $signature = 'puente:token-listar';

    protected $description = 'Lista los tokens de la API (sin mostrar su valor) y el modo de cada cobertura.';

    public function handle(): int
    {
        $this->table(['ID', 'Nombre', 'Cobertura', 'Estado', 'Vence', 'Último uso', 'Creado'], PuenteToken::query()->orderBy('id')->get()->map(fn (PuenteToken $t) => [
            $t->id,
            $t->nombre,
            $t->cobertura(),
            $t->revocado_en ? 'revocado' : ($t->expira_en && $t->expira_en->isPast() ? 'vencido' : 'vigente'),
            $t->expira_en?->toDateTimeString() ?? '—',
            $t->ultimo_uso_en?->toDateTimeString() ?? 'nunca',
            $t->created_at?->toDateTimeString(),
        ]));

        $this->table(['Cobertura', 'Modo', 'Rutas'], collect(config('api_tokens.coberturas', []))->map(fn (array $c, string $clave) => [
            $clave,
            in_array($clave, ['puente', 'territorio'], true) || strtolower(trim((string) $c['modo'])) !== 'opcional' ? 'obligatorio' : 'opcional',
            $c['nombre'],
        ])->values());

        return self::SUCCESS;
    }
}
