<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee los libros de resultados de simulación exportados desde AnyLogic.
 *
 * Un libro se reconoce como resultado de simulación cuando contiene al menos dos
 * de las hojas Minutos, Cada5min, Cada10min y Horas. A partir de ahí se valida
 * de forma estricta y cualquier problema se informa con hoja, fila y columna
 * (ValidationException). Los libros que no tienen esa estructura (por ejemplo,
 * datos de la comunidad o parámetros de diseño) devuelven null y se conservan
 * como documentos descargables.
 *
 * Formatos admitidos:
 *  - básico: 13 columnas (tiempo + 12 variables).
 *  - extendido: las 13 anteriores más el balance energético de batería y diésel
 *    (% estado de carga, Wh acum. excedente, Wh acum. diésel, L acum. combustible
 *    y Wh acum. de demanda no cubierta). Cada columna extendida es opcional.
 */
class SimulationResultsImport
{
    /** Tabla con las series de tiempo de los resultados de simulación (antes se llamaba solar_data). */
    public const TABLE = 'resultados_simulacion';

    /** Valor de `data_versions.tipo` para estos resultados (antes `solar`). */
    public const VERSION_TYPE = 'simulacion';

    /** @deprecated El nombre ya no determina si un libro es importable. */
    public const OFFICIAL_FILENAME = 'Resultados caso 1.xlsx';

    /** Encabezados del formato básico, en el orden del archivo original. */
    public const HEADERS = ['Hora', 'Caudal (m3/h)', 'Radiación solar (W/m2)', 'Teperatura (°C)', 'Velocidad del viento (m/s)', 'Potencia solar (W)', 'Potencia neta (W)', 'Energía almacenada (W)', 'Consumo planta (W)', 'Agua desalinizada (m3)', 'Salmuera (m3)', 'Lodos gruesos (N.º paquetes de 10 kg)', 'Lodos finos (N.º paquetes de 10 kg)'];

    /** Encabezados del formato extendido (resultados escenario 1). */
    public const EXTENDED_HEADERS = ['Minuto', 'Caudal (m3/h)', 'Radiación solar (W/m2)', 'Temperatura (°C)', 'Velocidad del viento (m/s)', 'Potencia solar (W)', 'Potencia neta (W)', 'Energía almacenada (W)', 'Consumo planta (W)', 'Agua desalinizada (m3)', 'Salmuera (m3)', 'Lodos gruesos (N.º paquetes de 10 kg)', 'Lodos finos (N.º paquetes de 10 kg)', '% estado de carga', 'Wh acum. excedente no aprovechado', 'Wh acum. entregados por el diesel', 'L acum. de combustible', 'Wh acum. de demanda no cubierta'];

    /** Columnas del formato básico en `resultados_simulacion`. */
    public const FIELDS = ['tiempo_minutos', 'caudal', 'radiacion_solar', 'temperatura', 'velocidad_viento', 'potencia_solar', 'potencia_neta', 'energia_almacenada', 'consumo_planta', 'agua_desalinizada', 'salmuera', 'lodos_gruesos', 'lodos_finos'];

    /** Columnas opcionales del balance energético en `resultados_simulacion`. */
    public const EXTENDED_FIELDS = ['estado_carga', 'excedente_no_aprovechado', 'energia_diesel', 'combustible_diesel', 'demanda_no_cubierta'];

    /** Hoja normalizada => intervalo en minutos. */
    public const SHEETS = ['minutos' => 1, 'cada5min' => 5, 'cada10min' => 10, 'horas' => 60];

    public const SHEET_LABELS = [1 => 'Minutos', 5 => 'Cada5min', 10 => 'Cada10min', 60 => 'Horas'];

    public const MAX_ROWS_PER_SHEET = 10000;

    private const MAX_REPORTED_ERRORS = 12;

    private const TOLERANCE = 1e-6;

    /**
     * Definición de columnas: etiqueta para mensajes, encabezados aceptados y regla.
     *
     * Reglas: time (entero ≥ 0, múltiplo del intervalo, sin duplicados), number,
     * positive (≥ 0), percent (0–100), cumulative (≥ 0 y no decreciente),
     * cumulative_int (entero ≥ 0 y no decreciente).
     */
    private const COLUMNS = [
        'tiempo_minutos' => ['label' => 'Minuto / Hora', 'headers' => ['Hora', 'Horas', 'Minuto', 'Minutos', 'Tiempo', 'Tiempo (min)'], 'rule' => 'time'],
        'caudal' => ['label' => 'Caudal (m3/h)', 'headers' => ['Caudal (m3/h)'], 'rule' => 'positive'],
        'radiacion_solar' => ['label' => 'Radiación solar (W/m2)', 'headers' => ['Radiación solar (W/m2)'], 'rule' => 'positive'],
        'temperatura' => ['label' => 'Temperatura (°C)', 'headers' => ['Temperatura (°C)', 'Teperatura (°C)'], 'rule' => 'number'],
        'velocidad_viento' => ['label' => 'Velocidad del viento (m/s)', 'headers' => ['Velocidad del viento (m/s)'], 'rule' => 'positive'],
        'potencia_solar' => ['label' => 'Potencia solar (W)', 'headers' => ['Potencia solar (W)'], 'rule' => 'positive'],
        'potencia_neta' => ['label' => 'Potencia neta (W)', 'headers' => ['Potencia neta (W)'], 'rule' => 'number'],
        'energia_almacenada' => ['label' => 'Energía almacenada (Wh)', 'headers' => ['Energía almacenada (W)', 'Energía almacenada (Wh)'], 'rule' => 'positive'],
        'consumo_planta' => ['label' => 'Consumo planta (W)', 'headers' => ['Consumo planta (W)'], 'rule' => 'positive'],
        'agua_desalinizada' => ['label' => 'Agua desalinizada (m3)', 'headers' => ['Agua desalinizada (m3)'], 'rule' => 'cumulative'],
        'salmuera' => ['label' => 'Salmuera (m3)', 'headers' => ['Salmuera (m3)'], 'rule' => 'cumulative'],
        'lodos_gruesos' => ['label' => 'Lodos gruesos (paquetes de 10 kg)', 'headers' => ['Lodos gruesos (N.º paquetes de 10 kg)'], 'rule' => 'cumulative_int'],
        'lodos_finos' => ['label' => 'Lodos finos (paquetes de 10 kg)', 'headers' => ['Lodos finos (N.º paquetes de 10 kg)'], 'rule' => 'cumulative_int'],
        'estado_carga' => ['label' => '% estado de carga', 'headers' => ['% estado de carga', 'Estado de carga (%)'], 'rule' => 'percent', 'optional' => true],
        'excedente_no_aprovechado' => ['label' => 'Wh acum. excedente no aprovechado', 'headers' => ['Wh acum. excedente no aprovechado'], 'rule' => 'cumulative', 'optional' => true],
        'energia_diesel' => ['label' => 'Wh acum. entregados por el diésel', 'headers' => ['Wh acum. entregados por el diesel'], 'rule' => 'cumulative', 'optional' => true],
        'combustible_diesel' => ['label' => 'L acum. de combustible', 'headers' => ['L acum. de combustible'], 'rule' => 'cumulative', 'optional' => true],
        'demanda_no_cubierta' => ['label' => 'Wh acum. de demanda no cubierta', 'headers' => ['Wh acum. de demanda no cubierta'], 'rule' => 'cumulative', 'optional' => true],
    ];

    /** @var array<int, string> */
    private array $errors = [];

    private int $errorCount = 0;

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii(str_replace(['³', '²'], ['3', '2'], $value))));
    }

    /** @return array<string, string> encabezado normalizado => campo */
    private function headerMap(): array
    {
        $map = [];
        foreach (self::COLUMNS as $field => $column) {
            foreach ($column['headers'] as $header) {
                $map[$this->normalize($header)] = $field;
            }
        }

        return $map;
    }

    /**
     * Devuelve los registros importables, o null si el libro no es un resultado de simulación.
     *
     * @param  string  $field  Campo del formulario al que se asocian los errores (por ejemplo `archivos.0`).
     * @return array{table: string, rows: array<int, array<string, int|float|null>>, columnas: array<int, string>, formato: string, registros: array<string, int>}|null
     *
     * @throws ValidationException cuando el libro tiene hojas de resultados pero no cumple el formato.
     */
    public function read(?UploadedFile $file, string $field = 'archivo'): ?array
    {
        if (! $file || ! in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls'], true)) {
            return null;
        }

        $this->errors = [];
        $this->errorCount = 0;
        $book = null;
        $path = $file->getRealPath();

        try {
            $reader = IOFactory::createReader(IOFactory::identify($path));
            $names = $reader->listWorksheetNames($path);
        } catch (\Throwable) {
            // No es un libro legible: se conserva como documento, igual que antes.
            return null;
        }

        $found = [];
        $duplicated = [];
        foreach ($names as $name) {
            $key = $this->normalize($name);
            if (isset(self::SHEETS[$key])) {
                isset($found[$key]) ? $duplicated[] = $name : $found[$key] = $name;
            }
        }
        // Una sola coincidencia (p. ej. una hoja «Horas» en otro tipo de libro) no basta para tratarlo como resultado.
        if (count($found) < 2) {
            return null;
        }

        $label = '«'.$file->getClientOriginalName().'»';
        foreach ($duplicated as $name) {
            $this->fail("la hoja «{$name}» está repetida.");
        }
        foreach (self::SHEETS as $key => $interval) {
            if (! isset($found[$key])) {
                $this->fail('falta la hoja «'.self::SHEET_LABELS[$interval].'». Un libro de resultados debe tener las hojas Minutos, Cada5min, Cada10min y Horas.');
            }
        }
        $this->throwIfFailed($field, $label);

        try {
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly(array_values($found));
            $book = $reader->load($path);

            $rows = [];
            $counts = [];
            $columns = null;
            foreach (self::SHEETS as $key => $interval) {
                $sheet = $book->getSheetByName($found[$key]);
                $sheetRows = $this->readSheet($sheet, $interval);
                if ($sheetRows === null) {
                    continue;
                }
                $sheetColumns = array_keys($sheetRows['columns']);
                if ($columns === null) {
                    $columns = $sheetColumns;
                } elseif ($sheetColumns != $columns) {
                    $this->fail('la hoja «'.self::SHEET_LABELS[$interval].'» no tiene las mismas columnas que la hoja «Minutos».');
                }
                $counts[self::SHEET_LABELS[$interval]] = count($sheetRows['rows']);
                array_push($rows, ...$sheetRows['rows']);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->fail('no fue posible leer el libro ('.$exception->getMessage().').');
        } finally {
            $book?->disconnectWorksheets();
        }

        $this->throwIfFailed($field, $label);

        $extended = array_values(array_intersect(self::EXTENDED_FIELDS, $columns ?? []));

        return [
            'table' => self::TABLE,
            'rows' => $rows,
            'columnas' => array_values(array_diff($columns ?? [], ['tiempo_minutos'])),
            'formato' => $extended ? 'extendido' : 'basico',
            'registros' => $counts,
        ];
    }

    /** @return array{columns: array<string, int>, rows: array<int, array<string, int|float|null>>}|null */
    private function readSheet(Worksheet $sheet, int $interval): ?array
    {
        $sheetName = self::SHEET_LABELS[$interval];
        $data = $sheet->toArray(null, false, false, false);
        $headerRow = array_shift($data) ?? [];

        // Ubicar cada columna por su encabezado; el orden de las columnas no importa.
        $map = $this->headerMap();
        $columns = [];
        foreach ($headerRow as $index => $header) {
            $text = trim((string) $header);
            if ($text === '') {
                $hasValues = collect($data)->contains(fn ($values) => ($values[$index] ?? null) !== null && ($values[$index] ?? null) !== '');
                if ($hasValues) {
                    $this->fail("hoja «{$sheetName}», columna ".Coordinate::stringFromColumnIndex($index + 1).': tiene datos pero no tiene encabezado.');
                }

                continue;
            }
            $field = $map[$this->normalize($text)] ?? null;
            if ($field === null) {
                $this->fail("hoja «{$sheetName}»: la columna «{$text}» no pertenece al formato de resultados.");

                continue;
            }
            if (isset($columns[$field])) {
                $this->fail("hoja «{$sheetName}»: la columna «{$text}» está repetida.");

                continue;
            }
            $columns[$field] = $index;
        }
        foreach (self::COLUMNS as $field => $column) {
            if (empty($column['optional']) && ! isset($columns[$field])) {
                $this->fail("hoja «{$sheetName}»: falta la columna «{$column['label']}».");
            }
        }
        if ($this->errors) {
            return null;
        }

        // Mantener el orden canónico de campos (afecta al hash de versión de datos).
        $ordered = [];
        foreach (array_keys(self::COLUMNS) as $field) {
            if (isset($columns[$field])) {
                $ordered[$field] = $columns[$field];
            }
        }

        $rows = [];
        $seen = [];
        $previous = [];
        foreach ($data as $offset => $values) {
            $excelRow = $offset + 2;
            if (! array_filter($values, fn ($value) => $value !== null && $value !== '')) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS_PER_SHEET) {
                $this->fail("hoja «{$sheetName}»: supera el máximo de ".number_format(self::MAX_ROWS_PER_SHEET, 0, ',', '.').' registros.');
                break;
            }

            $row = ['intervalo_minutos' => $interval];
            $valid = true;
            foreach ($ordered as $field => $index) {
                $value = $this->cellValue($sheet, $values[$index] ?? null, $index, $excelRow);
                $result = $this->validateValue($field, $value, $interval, $sheetName, $excelRow, $index);
                if ($result === false) {
                    $valid = false;

                    continue;
                }
                $row[$field] = $result;
            }
            if (! $valid) {
                continue;
            }

            $time = $row['tiempo_minutos'];
            $location = "hoja «{$sheetName}», fila {$excelRow}";
            if ($time % $interval !== 0) {
                $this->fail("{$location}: el tiempo {$time} min no es múltiplo del intervalo de {$interval} min.");

                continue;
            }
            if (isset($seen[$time])) {
                $this->fail("{$location}: el tiempo {$time} min está repetido (ya aparece en la fila {$seen[$time]}).");

                continue;
            }
            $seen[$time] = $excelRow;
            $rows[] = $row;
        }

        if (! $rows && ! $this->errors) {
            $this->fail("la hoja «{$sheetName}» no contiene registros.");
        }

        // Los acumulados no pueden disminuir con el tiempo.
        usort($rows, fn ($a, $b) => $a['tiempo_minutos'] <=> $b['tiempo_minutos']);
        foreach ($ordered as $field => $index) {
            if (! in_array(self::COLUMNS[$field]['rule'], ['cumulative', 'cumulative_int'], true)) {
                continue;
            }
            $last = null;
            foreach ($rows as $row) {
                $value = $row[$field];
                if ($value === null) {
                    continue;
                }
                if ($last !== null && $value < $last['value'] - self::TOLERANCE * max(1, abs($last['value']))) {
                    $this->fail("hoja «{$sheetName}», columna «".self::COLUMNS[$field]['label']."»: es un acumulado y disminuye de {$this->format($last['value'])} (minuto {$last['time']}) a {$this->format($value)} (minuto {$row['tiempo_minutos']}).");
                    break;
                }
                $last = ['value' => $value, 'time' => $row['tiempo_minutos']];
            }
        }

        return ['columns' => $ordered, 'rows' => $rows];
    }

    /** Usa el valor calculado que Excel guardó para las celdas con fórmula. */
    private function cellValue(Worksheet $sheet, mixed $value, int $index, int $excelRow): mixed
    {
        if (is_string($value) && str_starts_with($value, '=')) {
            $cached = $sheet->getCell([$index + 1, $excelRow])->getOldCalculatedValue();

            return $cached ?? $value;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /** @return int|float|null|false false cuando el valor no es válido (el error ya quedó registrado). */
    private function validateValue(string $field, mixed $value, int $interval, string $sheetName, int $excelRow, int $index): int|float|null|false
    {
        $rule = self::COLUMNS[$field]['rule'];
        $location = "hoja «{$sheetName}», fila {$excelRow}, columna «".self::COLUMNS[$field]['label'].'» ('.Coordinate::stringFromColumnIndex($index + 1).$excelRow.')';

        if ($value === null || $value === '') {
            if ($rule === 'time') {
                $this->fail("{$location}: el tiempo es obligatorio.");

                return false;
            }

            return null;
        }
        if (is_bool($value) || ! is_numeric($value) || ! is_finite((float) $value)) {
            $shown = is_scalar($value) ? Str::limit((string) $value, 30) : gettype($value);
            $hint = is_string($value) && preg_match('/^-?\d+,\d+$/', $value) ? ' Usa punto como separador decimal o guarda la celda como número.' : '';
            $this->fail("{$location}: «{$shown}» no es un número.{$hint}");

            return false;
        }

        $number = (float) $value;
        $integer = in_array($rule, ['time', 'cumulative_int'], true);
        if ($integer && (abs($number - round($number)) > self::TOLERANCE || $number > 4294967295)) {
            $this->fail("{$location}: debe ser un número entero (se recibió {$this->format($number)}).");

            return false;
        }
        if (in_array($rule, ['time', 'positive', 'percent', 'cumulative', 'cumulative_int'], true) && $number < -self::TOLERANCE) {
            $this->fail("{$location}: no puede ser negativo (se recibió {$this->format($number)}).");

            return false;
        }
        if ($rule === 'percent' && $number > 100 + self::TOLERANCE) {
            $this->fail("{$location}: es un porcentaje y no puede superar 100 (se recibió {$this->format($number)}).");

            return false;
        }

        if ($rule === 'time') {
            $minutes = (int) round($number);

            return $interval === 60 ? $minutes * 60 : $minutes;
        }

        return $integer ? (int) round($number) : $number;
    }

    private function format(float|int $value): string
    {
        return rtrim(rtrim(number_format($value, 6, ',', '.'), '0'), ',');
    }

    private function fail(string $message): void
    {
        $this->errorCount++;
        if (count($this->errors) < self::MAX_REPORTED_ERRORS) {
            $this->errors[] = $message;
        }
    }

    private function throwIfFailed(string $field, string $label): void
    {
        if (! $this->errors) {
            return;
        }
        $messages = array_map(fn ($message) => "{$label}: {$message}", $this->errors);
        $hidden = $this->errorCount - count($this->errors);
        if ($hidden > 0) {
            $messages[] = "{$label}: hay {$hidden} ".($hidden === 1 ? 'error adicional' : 'errores adicionales').'; corrige los anteriores y vuelve a cargar el archivo.';
        }
        $this->errors = [];
        $this->errorCount = 0;

        throw ValidationException::withMessages([$field => $messages]);
    }

    public function persist(int $scenarioId, int $fileId, array $import): void
    {
        DB::table(self::TABLE)->where('archivo_id', $fileId)->delete();
        $now = now();
        foreach (array_chunk($import['rows'], 200) as $chunk) {
            DB::table(self::TABLE)->insert(array_map(fn ($row) => $row + ['escenario_id' => $scenarioId, 'archivo_id' => $fileId, 'created_at' => $now, 'updated_at' => $now], $chunk));
        }
    }
}
