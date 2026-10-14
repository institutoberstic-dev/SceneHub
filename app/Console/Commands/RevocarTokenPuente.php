<?php

namespace App\Console\Commands;

use App\Models\PuenteToken;
use Illuminate\Console\Command;

class RevocarTokenPuente extends Command
{
    protected $signature = 'puente:token-revocar {id : ID del token (ver puente:token-listar)}';

    protected $description = 'Revoca un token del Puente de datos. Deja de funcionar de inmediato.';

    public function handle(): int
    {
        $token = PuenteToken::query()->find($this->argument('id'));
        if (! $token) {
            $this->error('No existe ese token.');

            return self::FAILURE;
        }
        if ($token->revocado_en) {
            $this->warn("El token #{$token->id} ya estaba revocado.");

            return self::SUCCESS;
        }
        $token->update(['revocado_en' => now()]);
        $this->info("Token #{$token->id} «{$token->nombre}» revocado.");

        return self::SUCCESS;
    }
}
