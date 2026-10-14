<?php

namespace App\Console\Commands;

use App\Models\PuenteToken;
use Illuminate\Console\Command;

class CrearTokenPuente extends Command
{
    protected $signature = 'puente:token-crear {nombre : Nombre del equipo o uso, p. ej. "PC del proyecto"} {--dias= : Días de vigencia (sin valor = no vence)}';

    protected $description = 'Crea un token de SOLO LECTURA para la app «Puente de datos». Se muestra una sola vez.';

    public function handle(): int
    {
        $dias = $this->option('dias');
        if ($dias !== null && (! ctype_digit((string) $dias) || (int) $dias < 1)) {
            $this->error('--dias debe ser un número entero mayor que cero.');

            return self::FAILURE;
        }
        [$token, $claro] = PuenteToken::emitir(mb_substr($this->argument('nombre'), 0, 120), $dias ? (int) $dias : null);

        $this->info("Token #{$token->id} «{$token->nombre}» creado (solo lectura).");
        $this->line('Cópielo ahora y péguelo en la app (Configuración → Token). No se volverá a mostrar:');
        $this->newLine();
        $this->line($claro);
        $this->newLine();
        $this->line($token->expira_en ? 'Vence: '.$token->expira_en->toDateTimeString() : 'Sin vencimiento. Revóquelo con: php artisan puente:token-revocar '.$token->id);

        return self::SUCCESS;
    }
}
