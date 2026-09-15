<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DataVersions
{
    // Caller holds the scenario lock and transaction during import.
    public function register(int $scenarioId, int $fileId, string $type, array $rows): object
    {
        $canonical = array_map(function ($row) {
            ksort($row);

            return $row;
        }, $rows);
        usort($canonical, fn ($a, $b) => [$a['intervalo_minutos'], $a['tiempo_minutos']] <=> [$b['intervalo_minutos'], $b['tiempo_minutos']]);
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
        $latest = DB::table('data_versions')->where('escenario_id', $scenarioId)->where('tipo', $type)->orderByDesc('revision')->first();
        if ($latest && $latest->sha256 === $hash) {
            return $latest;
        }
        $revision = ($latest->revision ?? 9) + 1;
        $id = DB::table('data_versions')->insertGetId(['escenario_id' => $scenarioId, 'archivo_id' => $fileId, 'tipo' => $type, 'revision' => $revision, 'version' => intdiv($revision, 10).'.'.($revision % 10), 'sha256' => $hash, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('data_versions')->find($id);
    }
}
