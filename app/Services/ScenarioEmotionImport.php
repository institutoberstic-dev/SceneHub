<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ScenarioEmotionImport
{
    public const METRICS = ['score_atencion', 'prob_angry', 'prob_disgust', 'prob_fear', 'prob_happy', 'prob_neutral', 'prob_sad', 'prob_surprise'];

    public const EMOTIONS = ['angry', 'disgust', 'fear', 'happy', 'neutral', 'sad', 'surprise', 'SIN DETECCION'];

    public function read(?UploadedFile $file): ?array
    {
        if (! $file || ! in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls'])) {
            return null;
        }

        $book = null;
        try {
            $type = IOFactory::identify($file->getRealPath());
            if (! in_array($type, ['Xlsx', 'Xls'])) {
                $this->fail('El archivo debe ser un libro Excel XLSX o XLS real.');
            }
            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);
            $book = $reader->load($file->getRealPath());
            $sheets = array_filter($book->getAllSheets(), fn ($sheet) => $sheet->getHighestDataRow() > 1 || $sheet->getCell('A1')->getValue() !== null);
            if (count($sheets) !== 1) {
                $this->fail('El Excel debe contener una única hoja con datos.');
            }
            $sheet = reset($sheets);
            if ($sheet->getHighestDataRow() > 100001 || ! in_array($sheet->getHighestDataColumn(), ['C', 'K', 'Q'])) {
                $this->fail('La estructura no corresponde al detalle (17 columnas) ni al promedio (11 columnas), o supera 100.000 filas.');
            }
            $data = $sheet->toArray(null, false, false, false);
            $headers = array_map(fn ($value) => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $value))), '_'), array_shift($data));
            if (count($headers) === 3) {
                $expected = ['id_meeting', 'nivel_atencion_prom', 'emocion_ganadora_prom'];
                if (array_diff($expected, $headers) || count(array_unique($headers)) !== 3) $this->fail('El promedio requiere id_meeting, nivel_atencion_prom y emocion_ganadora_prom.');
                $rows = []; $seen = [];
                foreach ($data as $index => $values) {
                    if (! array_filter($values, fn ($value) => $value !== null && $value !== '')) continue;
                    $row = array_combine($headers, $values);
                    $validator = Validator::make($row, ['id_meeting' => ['required', 'integer', 'min:1'], 'nivel_atencion_prom' => ['required', 'string', 'max:100'], 'emocion_ganadora_prom' => ['required', 'string', 'max:100']]);
                    if ($validator->fails()) $this->fail('Fila '.($index + 2).': '.implode(' ', $validator->errors()->all()));
                    $row['id_meeting'] = (int) $row['id_meeting'];
                    if (isset($seen[$row['id_meeting']])) $this->fail('Fila '.($index + 2).': id_meeting duplicado.');
                    $seen[$row['id_meeting']] = true;
                    $rows[] = $row;
                }
                if (! $rows) $this->fail('El Excel no contiene filas de datos.');
                return ['table' => 'emotions_prom', 'format' => 'categorical', 'rows' => $rows, 'source_file' => basename($file->getClientOriginalName())];
            }
            $average = in_array('score_atencion_prom', $headers, true);
            $metrics = array_map(fn ($metric) => $metric.($average ? '_prom' : ''), self::METRICS);
            $expected = $average
                ? ['id_persona', 'id_meeting', ...$metrics, 'emocion_prom']
                : ['id_persona', 'id_meeting', 'archivo', 'nivel_atencion', ...$metrics, 'emocion_ganadora', 'fecha', 'tiempo', 'validez', 'estatus_calidad_dama'];
            $missing = array_diff($expected, $headers);
            $extra = array_diff($headers, $expected);
            if ($missing || $extra || count(array_unique($headers)) !== count($headers)) {
                $this->fail('Encabezados inválidos. Faltan: '.implode(', ', $missing).'. No reconocidos: '.implode(', ', $extra).'. No se permiten columnas duplicadas.');
            }
            $rows = [];
            $seen = [];
            foreach ($data as $index => $values) {
                if (count(array_filter($values, fn ($value) => $value !== null && $value !== '')) === 0) {
                    continue;
                }
                $row = array_combine($headers, $values);
                foreach ($row as &$value) {
                    if (is_string($value)) {
                        $value = trim($value);
                    }
                }
                unset($value);
                $rules = ['id_persona' => ['required', 'integer', 'min:1'], 'id_meeting' => ['required', 'integer', 'min:1']];
                foreach ($metrics as $metric) {
                    $rules[$metric] = ['required', 'numeric', 'between:0,1'];
                }
                $emotion = $average ? 'emocion_prom' : 'emocion_ganadora';
                $row[$emotion] = strtoupper((string) $row[$emotion]) === 'SIN DETECCION' ? 'SIN DETECCION' : strtolower((string) $row[$emotion]);
                $rules[$emotion] = ['required', Rule::in(self::EMOTIONS)];
                if (! $average) {
                    $row['nivel_atencion'] = strtoupper((string) $row['nivel_atencion']);
                    $row['validez'] = match (strtoupper((string) $row['validez'])) {
                        'VALIDO', '1' => 1,
                        'NO VALIDO', '0' => 0,
                        default => $row['validez'],
                    };
                    if (is_numeric($row['fecha'])) {
                        $row['fecha'] = Date::excelToDateTimeObject($row['fecha'])->format('Y-m-d');
                    }
                    if (is_numeric($row['tiempo']) && $row['tiempo'] >= 0 && $row['tiempo'] < 1) {
                        $row['tiempo'] = Date::excelToDateTimeObject($row['tiempo'])->format('H:i:s');
                    }
                    $rules += [
                        'archivo' => ['required', 'string', 'max:255'],
                        'nivel_atencion' => ['required', Rule::in(['ATENTO', 'NO ATENTO', 'SIN DETECCION'])],
                        'fecha' => ['required', 'date_format:Y-m-d'],
                        'tiempo' => ['required', 'date_format:H:i:s'],
                        'validez' => ['required', 'boolean'],
                        'estatus_calidad_dama' => ['required', 'string', 'max:100'],
                    ];
                }
                $validator = Validator::make($row, $rules);
                if ($validator->fails()) {
                    $this->fail('Fila '.($index + 2).': '.implode(' ', $validator->errors()->all()));
                }
                if (! $average) {
                    $row['estatus_calidad_DAMA'] = $row['estatus_calidad_dama'];
                    unset($row['estatus_calidad_dama']);
                }
                $row['record_key'] = hash('sha256', json_encode([(int) $row['id_persona'], (int) $row['id_meeting'], $row['archivo'] ?? null]));
                if (isset($seen[$row['record_key']])) {
                    $this->fail('Fila '.($index + 2).': registro duplicado dentro del Excel.');
                }
                $seen[$row['record_key']] = true;
                $rows[] = $row;
            }
            if (! $rows) {
                $this->fail('El Excel no contiene filas de datos.');
            }

            return ['table' => $average ? 'emotions_prom' : 'emotions', 'rows' => $rows, 'source_file' => basename($file->getClientOriginalName())];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            $this->fail('No fue posible leer el Excel. Comprueba que no esté dañado ni protegido.');
        } finally {
            $book?->disconnectWorksheets();
        }
    }

    public function persist(int $scenarioId, ?array $import): void
    {
        if ($import === null) {
            return;
        }
        // Replace this source snapshot, including rows removed from a revised file.
        foreach (['emotions', 'emotions_prom'] as $table) {
            $query = DB::table($table)->where('escenario_id', $scenarioId)->where('source_file', $import['source_file']);
            if ($table === 'emotions_prom') $query->whereNull('data_version_id');
            $query->delete();
        }
        $now = now();
        foreach (array_chunk($import['rows'], 250) as $chunk) {
            $rows = array_map(fn ($row) => $row + ['escenario_id' => $scenarioId, 'source_file' => $import['source_file'], 'created_at' => $now, 'updated_at' => $now], $chunk);
            DB::table($import['table'])->upsert($rows, ['escenario_id', 'record_key'], array_diff(array_keys($rows[0]), ['created_at', 'escenario_id', 'record_key']));
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['archivo' => $message]);
    }
}
