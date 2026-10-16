<?php

namespace App\Console\Commands;

use App\Models\PuenteToken;
use Illuminate\Console\Command;

class CrearTokenPuente extends Command
{
    protected $signature = 'puente:token-crear
        {nombre : Nombre del equipo o uso, p. ej. "PC del proyecto" o "Unity gemelo"}
        {--alcance=puente : Cobertura: puente (app de escritorio), territorio (paquetes del PT1), simulacion, emociones, capturas (carga de imágenes) o unity (app Unity: las tres anteriores)}
        {--dias= : Días de vigencia (sin valor = no vence)}';

    protected $description = 'Crea un token para una cobertura de la API (solo lectura, salvo la carga de capturas). Se muestra una sola vez.';

    public function handle(): int
    {
        $dias = $this->option('dias');
        if ($dias !== null && (! ctype_digit((string) $dias) || (int) $dias < 1)) {
            $this->error('--dias debe ser un número entero mayor que cero.');

            return self::FAILURE;
        }
        $cobertura = strtolower(trim((string) $this->option('alcance')));
        if (! in_array($cobertura, PuenteToken::coberturas(), true)) {
            $this->error('--alcance debe ser uno de: '.implode(', ', PuenteToken::coberturas()).'.');

            return self::FAILURE;
        }
        [$token, $claro] = PuenteToken::emitir(mb_substr($this->argument('nombre'), 0, 120), $dias ? (int) $dias : null, $cobertura);

        $permiso = in_array($cobertura, ['capturas', 'unity'], true) ? 'lectura y carga de capturas' : 'solo lectura';
        $this->info("Token #{$token->id} «{$token->nombre}» creado ({$permiso}, cobertura: {$cobertura}).");
        $this->line('Cobertura: '.config("api_tokens.coberturas.{$cobertura}.nombre"));
        $this->line('Cópielo ahora y guárdelo en el cliente (app, Unity…). No se volverá a mostrar:');
        $this->newLine();
        $this->line($claro);
        $this->newLine();
        $this->line($token->expira_en ? 'Vence: '.$token->expira_en->toDateTimeString() : 'Sin vencimiento. Revóquelo con: php artisan puente:token-revocar '.$token->id);
        $this->line('Antes de compartir esta pantalla, ejecute «clear».');

        return self::SUCCESS;
    }
}
