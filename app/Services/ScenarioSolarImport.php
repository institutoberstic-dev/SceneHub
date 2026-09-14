<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ScenarioSolarImport
{
    public const HEADERS = ['Hora', 'Caudal (m3/h)', 'Radiación solar (W/m2)', 'Teperatura (°C)', 'Velocidad del viento (m/s)', 'Potencia solar (W)', 'Potencia neta (W)', 'Energía almacenada (W)', 'Consumo planta (W)', 'Agua desalinizada (m3)', 'Salmuera (m3)', 'Lodos gruesos (N.º paquetes de 10 kg)', 'Lodos finos (N.º paquetes de 10 kg)'];
    public const FIELDS = ['tiempo_minutos', 'caudal', 'radiacion_solar', 'temperatura', 'velocidad_viento', 'potencia_solar', 'potencia_neta', 'energia_almacenada', 'consumo_planta', 'agua_desalinizada', 'salmuera', 'lodos_gruesos', 'lodos_finos'];

    private function normalize(string $value): string
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii(str_replace(['³', '²'], ['3', '2'], $value))));
        return str_replace('temperatura', 'teperatura', $normalized);
    }

    public function read(?UploadedFile $file): ?array
    {
        if (! $file || ! in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls'])) return null;
        $book = null;
        try {
            $reader = IOFactory::createReader(IOFactory::identify($file->getRealPath()));
            $names = $reader->listWorksheetNames($file->getRealPath());
            $wanted = ['minutos' => 1, 'cada5min' => 5, 'cada10min' => 10];
            $found = [];
            foreach ($names as $name) {
                $key = $this->normalize($name);
                if (isset($wanted[$key])) {
                    if (isset($found[$key])) $this->fail('Hay hojas solares duplicadas.');
                    $found[$key] = $name;
                }
            }
            if (! $found) return null;
            if (count($found) !== 3) $this->fail('Faltan hojas solares: '.implode(', ', array_diff(array_keys($wanted), array_keys($found))).'.');
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly(array_values($found));
            $book = $reader->load($file->getRealPath());
            $expected = array_map(fn ($h) => $this->normalize($h), self::HEADERS);
            $rows = [];
            foreach ($found as $key => $name) {
                $sheet = $book->getSheetByName($name);
                if ($sheet->getHighestDataColumn() !== 'M' || $sheet->getHighestDataRow() > 10001) $this->fail("Hoja $name: se requieren 13 columnas y un máximo de 10.000 registros.");
                $data = $sheet->toArray(null, false, false, false);
                $headers = array_map(fn ($h) => $this->normalize((string) $h), array_shift($data));
                if (count(array_unique($headers)) !== 13 || array_diff($expected, $headers)) $this->fail("Hoja $name: encabezados incompatibles con el formato solar y sus unidades.");
                $positions = array_map(fn ($h) => array_search($h, $headers, true), $expected);
                $seen = [];
                foreach ($data as $index => $values) {
                    if (! array_filter($values, fn ($v) => $v !== null && $v !== '')) continue;
                    $row = ['intervalo_minutos' => $wanted[$key]];
                    foreach (self::FIELDS as $i => $field) {
                        $value = $values[$positions[$i]];
                        if ($value === null || $value === '') {
                            if ($i === 0) $this->fail("Hoja $name, fila ".($index + 2).': falta el tiempo.');
                            $row[$field] = null;
                            continue;
                        }
                        if (! is_numeric($value) || ! is_finite((float) $value)) $this->fail("Hoja $name, fila ".($index + 2).": $field debe ser numérico, sin fórmulas.");
                        if (($i === 0 || $i >= 11) && ((float) $value < 0 || floor((float) $value) != (float) $value || (float) $value > 4294967295)) $this->fail("Hoja $name: tiempo y paquetes deben ser enteros no negativos.");
                        $row[$field] = ($i === 0 || $i >= 11) ? (int) $value : (float) $value;
                    }
                    if ($row['tiempo_minutos'] % $wanted[$key] !== 0 || isset($seen[$row['tiempo_minutos']])) $this->fail("Hoja $name: tiempo duplicado o incompatible con el intervalo.");
                    $seen[$row['tiempo_minutos']] = true;
                    $rows[] = $row;
                }
                if (! $seen) $this->fail("Hoja $name: no contiene mediciones.");
            }
            return ['table' => 'solar_data', 'rows' => $rows];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $this->fail('No fue posible leer el Excel. Comprueba que no esté dañado ni protegido.');
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

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['archivo' => $message]);
    }
}
