<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DataVersions
{
    // Caller holds the scenario lock and transaction during import.
    public function register(int $scenarioId, int $fileId, string $type, array $rows): object
    {
        $canonical = array_map(function ($row) { ksort($row); return $row; }, $rows);
        usort($canonical, fn ($a, $b) => [$a['intervalo_minutos'] ?? 0, $a['tiempo_minutos'] ?? $a['id_meeting']] <=> [$b['intervalo_minutos'] ?? 0, $b['tiempo_minutos'] ?? $b['id_meeting']]);
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
        $latest = DB::table('data_versions')->where('escenario_id', $scenarioId)->where('tipo', $type)->orderByDesc('revision')->first();
        if ($latest && $latest->sha256 === $hash) return $latest;
        $revision = ($latest->revision ?? 9) + 1;
        $id = DB::table('data_versions')->insertGetId(['escenario_id' => $scenarioId, 'archivo_id' => $fileId, 'tipo' => $type, 'revision' => $revision, 'version' => intdiv($revision, 10).'.'.($revision % 10), 'sha256' => $hash, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('data_versions')->find($id);
    }

    public function persistAverage(int $scenarioId, int $fileId, array $import): void
    {
        $version = $this->register($scenarioId, $fileId, 'emociones_promedio', $import['rows']);
        if (DB::table('emotions_prom')->where('data_version_id', $version->id)->exists()) return;
        foreach (array_chunk($import['rows'], 100) as $chunk) {
            DB::table('emotions_prom')->insert(array_map(fn ($row) => $row + ['escenario_id' => $scenarioId, 'data_version_id' => $version->id, 'source_file' => $import['source_file'], 'record_key' => hash('sha256', 'version:'.$version->id.':'.$row['id_meeting']), 'created_at' => now(), 'updated_at' => now()], $chunk));
        }
    }
}
