<?php

namespace App\Console\Commands;

use App\Models\PuenteToken;
use Illuminate\Console\Command;

class EliminarTokenPuente extends Command
{
    protected $signature = 'puente:token-eliminar
        {id? : ID del token (ver puente:token-listar)}
        {--revocados : Elimina todos los tokens revocados o vencidos}
        {--force : No pide confirmación}';

    protected $description = 'Elimina tokens de la API de forma definitiva (deja de funcionar al instante y no se puede deshacer).';

    public function handle(): int
    {
        $id = $this->argument('id');
        if (($id === null) === ! $this->option('revocados')) {
            $this->error('Indique un ID o la opción --revocados (no ambos). Ejemplos: puente:token-eliminar 4 · puente:token-eliminar --revocados');

            return self::FAILURE;
        }

        if ($this->option('revocados')) {
            $consulta = PuenteToken::query()->where(fn ($q) => $q->whereNotNull('revocado_en')->orWhere('expira_en', '<=', now()));
            $total = $consulta->count();
            if ($total === 0) {
                $this->info('No hay tokens revocados ni vencidos.');

                return self::SUCCESS;
            }
            if (! $this->option('force') && ! $this->confirm("Se eliminarán {$total} token(s) revocados o vencidos. ¿Continuar?")) {
                $this->warn('Cancelado. No se eliminó nada.');

                return self::SUCCESS;
            }
            $consulta->delete();
            $this->info("Eliminados {$total} token(s) revocados o vencidos.");

            return self::SUCCESS;
        }

        $token = PuenteToken::query()->find($id);
        if (! $token) {
            $this->error('No existe ese token.');

            return self::FAILURE;
        }
        $vigente = ! $token->revocado_en && (! $token->expira_en || $token->expira_en->isFuture());
        $aviso = "Se eliminará el token #{$token->id} «{$token->nombre}» (cobertura: {$token->cobertura()})."
            .($vigente ? ' Está VIGENTE: el cliente que lo usa perderá el acceso de inmediato.' : '');
        if (! $this->option('force') && ! $this->confirm($aviso.' ¿Continuar?')) {
            $this->warn('Cancelado. No se eliminó nada.');

            return self::SUCCESS;
        }
        $token->delete();
        $this->info("Token #{$token->id} «{$token->nombre}» eliminado.");

        return self::SUCCESS;
    }
}
