<?php

namespace App\Console\Commands;

use App\Models\PuenteToken;
use Illuminate\Console\Command;

class ListarTokensPuente extends Command
{
    protected $signature = 'puente:token-listar';

    protected $description = 'Lista los tokens del Puente de datos (sin mostrar su valor).';

    public function handle(): int
    {
        $this->table(['ID', 'Nombre', 'Estado', 'Vence', 'Último uso', 'Creado'], PuenteToken::query()->orderBy('id')->get()->map(fn (PuenteToken $t) => [
            $t->id,
            $t->nombre,
            $t->revocado_en ? 'revocado' : ($t->expira_en && $t->expira_en->isPast() ? 'vencido' : 'vigente'),
            $t->expira_en?->toDateTimeString() ?? '—',
            $t->ultimo_uso_en?->toDateTimeString() ?? 'nunca',
            $t->created_at?->toDateTimeString(),
        ]));

        return self::SUCCESS;
    }
}
