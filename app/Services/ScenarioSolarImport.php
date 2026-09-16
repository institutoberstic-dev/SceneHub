<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ScenarioSolarImport
{
    /** @deprecated El nombre ya no determina si un libro es importable. */
    public const OFFICIAL_FILENAME = 'Resultados caso 1.xlsx';

    public const HEADERS = ['Hora', 'Caudal (m3/h)', 'Radiación solar (W/m2)', 'Teperatura (°C)', 'Velocidad del viento (m/s)', 'Potencia solar (W)', 'Potencia neta (W)', 'Energía almacenada (W)', 'Consumo planta (W)', 'Agua desalinizada (m3)', 'Salmuera (m3)', 'Lodos gruesos (N.º paquetes de 10 kg)', 'Lodos finos (N.º paquetes de 10 kg)'];

    public const FIELDS = ['tiempo_minutos', 'caudal', 'radiacion_solar', 'temperatura', 'velocidad_viento', 'potencia_solar', 'potencia_neta', 'energia_almacenada', 'consumo_planta', 'agua_desalinizada', 'salmuera', 'lodos_gruesos', 'lodos_finos'];

    private function normalize(string $value): string
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii(str_replace(['³', '²'], ['3', '2'], $value))));

        return str_replace('temperatura', 'teperatura', $normalized);
    }

    public function read(?UploadedFile $file): ?array
    {
        if (! $file) {
            return null;
        }
        if (! in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls'], true)) {
            return null;
        }

        $book = null;
        try {
            $reader = IOFactory::createReader(IOFactory::identify($file->getRealPath()));
            $names = $reader->listWorksheetNames($file->getRealPath());
            $wanted = ['minutos' => 1, 'cada5min' => 5, 'cada10min' => 10, 'horas' => 60];
            $found = [];
            foreach ($names as $name) {
                $key = $this->normalize($name);
                if (isset($wanted[$key])) {
                    if (isset($found[$key])) {
                        return null;
                    }
                    $found[$key] = $name;
                }
            }
            if (count($found) !== count($wanted)) {
                return null;
            }
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly(array_values($found));
            $book = $reader->load($file->getRealPath());
            $expected = array_map(fn ($h) => $this->normalize($h), self::HEADERS);
            $rows = [];
            foreach ($found as $key => $name) {
                $sheet = $book->getSheetByName($name);
                if ($sheet->getHighestDataColumn() !== 'M' || $sheet->getHighestDataRow() > 10001) {
                    return null;
                }
                $data = $sheet->toArray(null, false, false, false);
                $headers = array_map(fn ($h) => $this->normalize((string) $h), array_shift($data));
                if (count(array_unique($headers)) !== 13 || array_diff($expected, $headers)) {
                    return null;
                }
                $positions = array_map(fn ($h) => array_search($h, $headers, true), $expected);
                $seen = [];
                foreach ($data as $index => $values) {
                    if (! array_filter($values, fn ($v) => $v !== null && $v !== '')) {
                        continue;
                    }
                    $row = ['intervalo_minutos' => $wanted[$key]];
                    foreach (self::FIELDS as $i => $field) {
                        $value = $values[$positions[$i]];
                        if ($value === null || $value === '') {
                            if ($i === 0) {
                                return null;
                            }
                            $row[$field] = null;

                            continue;
                        }
                        if (! is_numeric($value) || ! is_finite((float) $value)) {
                            return null;
                        }
                        if (($i === 0 || $i >= 11) && ((float) $value < 0 || floor((float) $value) != (float) $value || (float) $value > 4294967295)) {
                            return null;
                        }
                        if ($i === 0 && $key === 'horas') {
                            $value = (float) $value * 60;
                        }
                        $row[$field] = ($i === 0 || $i >= 11) ? (int) $value : (float) $value;
                    }
                    if ($row['tiempo_minutos'] % $wanted[$key] !== 0 || isset($seen[$row['tiempo_minutos']])) {
                        return null;
                    }
                    $seen[$row['tiempo_minutos']] = true;
                    $rows[] = $row;
                }
                if (! $seen) {
                    return null;
                }
            }

            return ['table' => 'solar_data', 'rows' => $rows];
        } catch (\Throwable $e) {
            // Un Excel válido como documento puede no pertenecer al formato importable.
            // En ese caso se conserva para descarga y no se rechaza toda la carga.
            return null;
        } finally {
            $book?->disconnectWorksheets();
        }
    }

    public function persist(int $scenarioId, int $fileId, array $import): void
    {
        DB::table('solar_data')->where('archivo_id', $fileId)->delete();
        foreach (array_chunk($import['rows'], 100) as $chunk) {
            DB::table('solar_data')->insert(array_map(fn ($row) => $row + ['escenario_id' => $scenarioId, 'archivo_id' => $fileId, 'created_at' => now(), 'updated_at' => now()], $chunk));
        }
    }
}
