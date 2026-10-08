<?php

namespace App\Services;

use App\Models\Escenario;
use App\Models\EscenarioContenido;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Nombres canónicos de los archivos de un escenario.
 *
 *  - Libro de resultados de simulación (se reconoce por sus hojas, no por el nombre):
 *    «resultados escenario {número del escenario}.xlsx». Volver a subirlo con otro
 *    nombre lo reemplaza (nueva versión) en lugar de crear un archivo paralelo.
 *  - Informe (el nombre dice «informe», «reporte»…, en cualquier formato: Word, PDF…):
 *    «informe escenario {número del informe}.{extensión}». Solo se actualiza con un archivo
 *    más reciente que el guardado (fecha File.lastModified enviada por el navegador). El número
 *    se toma del nombre subido («Informe_esc 2.docx» ⇒ 2). Si el nombre no trae número se
 *    reutiliza el del informe con el mismo contenido o con el mismo nombre original y, si no
 *    hay ninguno, se usa el siguiente consecutivo. Mismo número ⇒ reemplaza; número nuevo ⇒
 *    informe nuevo.
 *  - Otros documentos: conservan su nombre.
 *
 * La vista previa de la carga (resources/js/components/scenarioFilePlan.js) aplica las mismas
 * reglas para mostrar los nombres antes de guardar; este servicio es la referencia.
 */
class ScenarioFiles
{
    public const RESULTS = 'resultados';

    public const REPORT = 'informe';

    public const DOCUMENT = 'documento';

    /** Clase de archivo => valor guardado en `escenario_contenidos.tipo`. */
    public const CONTENT_TYPES = [self::RESULTS => 'datos', self::REPORT => 'informe', self::DOCUMENT => 'documento'];

    /** Extensiones admitidas en las cargas de escenarios. Un informe puede tener cualquiera de ellas. */
    public const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'doc', 'docx', 'pdf'];

    /** Palabras del nombre que identifican un informe («Informe_esc 1.pdf», «Reporte final.docx»). */
    private const REPORT_NAME_WORDS = ['informe', 'informes', 'inf', 'reporte', 'reportes', 'report', 'reports'];

    public const RESULTS_PREFIX = 'resultados escenario';

    public const REPORT_PREFIX = 'informe escenario';

    public const MAX_SCENARIO_NUMBER = 9999;

    public const MAX_REPORT_NUMBER = 999;

    private const SCENARIO_KEYWORDS = ['escenario', 'esc', 'caso', 'scenario'];

    private const REPORT_KEYWORDS = ['informe', 'informes', 'inf', 'reporte', 'reportes', 'report', 'escenario', 'esc'];

    /** Palabras que pueden ir entre la palabra clave y el número («Informe No. 3», «Informe de escenario 2», «Escenario Nº 2»). */
    private const FILLERS = ['no', 'n', 'nro', 'num', 'numero', 'o', 'de', 'del', 'el'];

    /** @var array<string, string> ruta => sha256 */
    private array $hashes = [];

    public static function resultsName(int $scenarioNumber, string $extension): string
    {
        return self::RESULTS_PREFIX.' '.$scenarioNumber.'.'.strtolower($extension);
    }

    public static function reportName(int $number, string $extension): string
    {
        return self::REPORT_PREFIX.' '.$number.'.'.strtolower($extension);
    }

    /**
     * Clase del archivo: resultados si el importador reconoció sus hojas; informe si el nombre
     * lo dice («informe», «reporte»…), sin importar el formato (Word, PDF…); documento en otro caso.
     */
    public static function kind(UploadedFile $file, ?array $import): string
    {
        if ($import !== null) {
            return self::RESULTS;
        }

        return self::isReportName($file->getClientOriginalName()) ? self::REPORT : self::DOCUMENT;
    }

    public static function isReportName(string $fileName): bool
    {
        return array_intersect(self::tokens(pathinfo($fileName, PATHINFO_FILENAME)), self::REPORT_NAME_WORDS) !== [];
    }

    /** @return array<int, string> palabras y números del texto, en minúsculas y sin tildes */
    private static function tokens(string $text): array
    {
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace('/(?<=[a-z])(?=\d)|(?<=\d)(?=[a-z])/', ' ', $text);

        return preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** Fecha del archivo enviada por el navegador (File.lastModified, ms) a segundos; null si no es válida. */
    public static function normalizeDate(mixed $milliseconds): ?int
    {
        if (! is_numeric($milliseconds) || (int) $milliseconds <= 0) {
            return null;
        }
        $seconds = intdiv((int) $milliseconds, 1000);

        return $seconds > time() + 86400 ? null : $seconds;
    }

    /** @param  bool  $isFileName  false para nombres de escenario («Escenario No. 2» no tiene extensión). */
    public static function scenarioNumberFromName(string $name, bool $isFileName = true): ?int
    {
        return self::numberFromName($name, self::SCENARIO_KEYWORDS, self::MAX_SCENARIO_NUMBER, $isFileName);
    }

    public static function reportNumberFromName(string $fileName): ?int
    {
        return self::numberFromName($fileName, self::REPORT_KEYWORDS, self::MAX_REPORT_NUMBER);
    }

    /**
     * Número escrito en un nombre, tolerante a errores de escritura: «Informe_esc 1»,
     * «informe escenario1», «INFORME No. 3», «resultados escenario 1 1», «Escenario Nº 2».
     * Solo cuenta el número que sigue a una palabra clave: «Informe final (1)» o
     * «Informe v2» no traen número de informe, ni las fechas («2026-09-16»).
     */
    public static function numberFromName(string $name, array $keywords, int $max, bool $isFileName = true): ?int
    {
        $tokens = self::tokens($isFileName ? pathinfo($name, PATHINFO_FILENAME) : $name);
        $valid = fn (string $token) => ctype_digit($token) && strlen($token) <= strlen((string) $max) && (int) $token >= 1 && (int) $token <= $max;

        foreach ($tokens as $index => $token) {
            if (! in_array($token, $keywords, true)) {
                continue;
            }
            for ($next = $index + 1; $next < count($tokens); $next++) {
                if (in_array($tokens[$next], self::FILLERS, true)) {
                    continue;
                }
                if ($valid($tokens[$next])) {
                    return (int) $tokens[$next];
                }
                break;
            }
        }

        return null;
    }

    /** @return array<int, int> números de escenario en uso */
    public static function usedScenarioNumbers(): array
    {
        return Escenario::query()->whereNotNull('numero')->orderBy('numero')->pluck('numero')->map(fn ($number) => (int) $number)->all();
    }

    /** El número preferido si está libre; si no, el menor número disponible (sin escenarios ⇒ 1). */
    public static function nextScenarioNumber(?int $preferred = null): int
    {
        $used = array_flip(self::usedScenarioNumbers());
        if ($preferred !== null && $preferred >= 1 && $preferred <= self::MAX_SCENARIO_NUMBER && ! isset($used[$preferred])) {
            return $preferred;
        }
        $number = 1;
        while (isset($used[$number])) {
            $number++;
        }

        return $number;
    }

    /** @return array{tipo: string, numero: int|null, extension: string} */
    public function describe(string $name): array
    {
        if (preg_match('/^resultados escenario (\d+)\.(xlsx?)$/i', $name, $match)) {
            return ['tipo' => self::RESULTS, 'numero' => (int) $match[1], 'extension' => strtolower($match[2])];
        }
        if (preg_match('/^informe escenario (\d+)\.([a-z0-9]+)$/i', $name, $match)) {
            return ['tipo' => self::REPORT, 'numero' => (int) $match[1], 'extension' => strtolower($match[2])];
        }

        return ['tipo' => self::DOCUMENT, 'numero' => null, 'extension' => strtolower(pathinfo($name, PATHINFO_EXTENSION))];
    }

    /**
     * Última copia de cada archivo del escenario (las versiones son instantáneas acumulativas,
     * pero se revisan todas por si alguna carpeta quedó incompleta).
     *
     * @return array<string, array{nombre: string, path: string, version: float, tipo: string, numero: int|null, extension: string}> nombre en minúsculas => archivo
     */
    public function existingFiles(?string $root): array
    {
        if (! $root || ! File::isDirectory($root)) {
            return [];
        }
        $directories = collect(File::directories($root))
            ->filter(fn (string $directory) => is_numeric(basename($directory)))
            ->sortByDesc(fn (string $directory) => (float) basename($directory));

        $files = [];
        foreach ($directories as $directory) {
            foreach (File::files($directory) as $file) {
                $key = Str::lower($file->getFilename());
                if (isset($files[$key])) {
                    continue;
                }
                $files[$key] = [
                    'nombre' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'version' => (float) basename($directory),
                ] + $this->describe($file->getFilename());
            }
        }

        return $files;
    }

    /**
     * @return array<int, array{nombre: string, tipo: string, numero: int|null, extension: string, version: string, sha256: string, resultados_anterior: bool}>
     */
    public function existingSummary(?string $root, ?int $scenarioId = null): array
    {
        $legacy = array_flip(array_map(fn (string $name) => Str::lower($name), $this->legacyResultsNames($scenarioId)));
        $dates = $this->storedDates($scenarioId);

        return collect($this->existingFiles($root))->map(fn (array $file) => [
            'nombre' => $file['nombre'],
            'tipo' => $file['tipo'],
            'numero' => $file['numero'],
            'extension' => $file['extension'],
            'version' => number_format($file['version'], 1, '.', ''),
            'sha256' => $this->hash($file['path']),
            // Fecha del archivo guardado (segundos); un informe solo se actualiza con uno más reciente.
            'fecha_archivo' => $dates[Str::lower($file['nombre'])] ?? null,
            // Libro de resultados guardado con un nombre anterior: el próximo libro lo reemplaza.
            'resultados_anterior' => isset($legacy[Str::lower($file['nombre'])]),
        ])->values()->all();
    }

    /**
     * Libros de resultados guardados antes de los nombres canónicos («Resultados caso 1.xlsx»).
     *
     * @return array<int, string>
     */
    public function legacyResultsNames(?int $scenarioId): array
    {
        if (! $scenarioId) {
            return [];
        }

        return EscenarioContenido::query()
            ->where('escenario_id', $scenarioId)
            ->where('tipo', self::CONTENT_TYPES[self::RESULTS])
            ->pluck('ruta')
            ->map(fn (string $path) => basename(str_replace('\\', '/', $path)))
            ->filter(fn (string $name) => $this->describe($name)['tipo'] !== self::RESULTS)
            ->unique(fn (string $name) => Str::lower($name))
            ->values()
            ->all();
    }

    /**
     * Número que el informe conserva sin depender de su posición en la carga: el de su
     * nombre, el del informe con el mismo contenido o el del informe subido antes con el
     * mismo nombre original. Null ⇒ informe nuevo con el siguiente consecutivo.
     *
     * @param  array<int, array{path: string}>  $reports  número => informe existente
     * @return array{0: int|null, 1: string|null} número y origen (nombre|contenido|nombre_original)
     */
    public function fixedReportNumber(UploadedFile $file, array $reports, ?int $scenarioId): array
    {
        $original = basename($file->getClientOriginalName());
        if ($number = self::reportNumberFromName($original)) {
            return [$number, 'nombre'];
        }
        $hash = $this->hash($file->getRealPath());
        foreach ($reports as $number => $report) {
            if ($this->hash($report['path']) === $hash) {
                return [$number, 'contenido'];
            }
        }
        if ($scenarioId) {
            $previous = EscenarioContenido::query()
                ->where('escenario_id', $scenarioId)
                ->where('tipo', self::CONTENT_TYPES[self::REPORT])
                ->whereRaw('LOWER(nombre_original) = ?', [Str::lower($original)])
                ->latest('id')
                ->value('nombre');
            $described = $previous ? $this->describe($previous) : null;
            if ($described && $described['tipo'] === self::REPORT) {
                return [$described['numero'], 'nombre_original'];
            }
        }

        return [null, null];
    }

    /**
     * Fecha (segundos) del archivo de cada nombre guardado, según la última carga que lo registró.
     *
     * @return array<string, int> nombre en minúsculas => fecha
     */
    public function storedDates(?int $scenarioId): array
    {
        if (! $scenarioId) {
            return [];
        }
        $dates = [];
        EscenarioContenido::query()->where('escenario_id', $scenarioId)->whereNotNull('fecha_archivo')->orderBy('id')
            ->get(['ruta', 'fecha_archivo'])
            ->each(function (EscenarioContenido $row) use (&$dates) {
                $dates[Str::lower(basename(str_replace('\\', '/', $row->ruta)))] = $row->fecha_archivo->getTimestamp();
            });

        return $dates;
    }

    /** Un documento que no es libro de resultados no puede llevar el nombre reservado para ese libro. */
    public function reservedNameError(string $original): ?string
    {
        $described = $this->describe(basename($original));

        return $described['tipo'] === self::RESULTS
            ? "«{$original}»: ese nombre está reservado para el libro de resultados del escenario, pero el archivo no contiene las hojas Minutos, Cada5min, Cada10min y Horas."
            : null;
    }

    /** @return array<int, array{nombre: string, path: string, extension: string}> número => informe */
    public function reportsIn(array $existing): array
    {
        $reports = [];
        foreach ($existing as $file) {
            if ($file['tipo'] === self::REPORT) {
                $reports[$file['numero']] = $file;
            }
        }
        ksort($reports);

        return $reports;
    }

    /**
     * Calcula el nombre con el que se guardará cada archivo y detecta conflictos.
     *
     * @param  array<string, UploadedFile>  $files  campo del formulario => archivo
     * @param  array<string, string>  $kinds  campo => clase (resultados|informe|documento)
     * @param  string|null  $root  carpeta del escenario (null si es nuevo)
     * @param  string|null  $customName  nombre visible opcional (solo documentos, carga de un archivo)
     * @param  array<string, int|null>  $dates  campo => fecha del archivo en segundos (File.lastModified)
     * @return array{items: array<string, array<string, mixed>>, errors: array<string, array<int, string>>}
     */
    public function plan(array $files, array $kinds, int $scenarioNumber, ?string $root = null, ?int $scenarioId = null, ?string $customName = null, array $dates = []): array
    {
        $existing = $this->existingFiles($root);
        $reports = $this->reportsIn($existing);
        $items = [];
        $errors = [];

        foreach ($files as $field => $file) {
            $kind = $kinds[$field];
            $original = basename($file->getClientOriginalName());
            $extension = strtolower($file->getClientOriginalExtension());
            $item = [
                'campo' => $field,
                'file' => $file,
                'tipo' => $kind,
                'nombre_original' => $original,
                'extension' => $extension,
                'numero' => null,
                'origen_numero' => null,
                'nombre' => $original,
                'fecha' => $dates[$field] ?? null,
                'advertencias' => [],
            ];

            if ($kind === self::RESULTS) {
                $item['numero'] = $scenarioNumber;
                $item['nombre'] = self::resultsName($scenarioNumber, $extension);
                $hinted = self::scenarioNumberFromName($original);
                if ($hinted !== null && $hinted !== $scenarioNumber) {
                    $item['advertencias'][] = "El nombre indica el escenario {$hinted}; se guardará como «{$item['nombre']}».";
                }
            } elseif ($kind === self::REPORT) {
                [$item['numero'], $item['origen_numero']] = $this->fixedReportNumber($file, $reports, $scenarioId);
            } elseif ($reserved = $this->reservedNameError($original)) {
                $errors[$field][] = $reserved;
            }
            $items[$field] = $item;
        }

        // Informes sin número: siguiente consecutivo después del mayor número usado.
        $used = array_keys($reports);
        foreach ($items as $item) {
            if ($item['tipo'] === self::REPORT && $item['numero'] !== null) {
                $used[] = $item['numero'];
            }
        }
        foreach ($items as $field => $item) {
            if ($item['tipo'] !== self::REPORT) {
                continue;
            }
            if ($item['numero'] === null) {
                $item['numero'] = ($used ? max($used) : 0) + 1;
                $item['origen_numero'] = 'consecutivo';
                $used[] = $item['numero'];
            }
            $item['nombre'] = self::reportName($item['numero'], $item['extension']);
            $items[$field] = $item;
        }

        $taken = [];
        foreach ($items as $field => $item) {
            // El mismo número cuenta como el mismo archivo aunque cambie la extensión (.xls/.xlsx, .doc/.docx).
            $key = $item['tipo'] === self::DOCUMENT ? 'documento:'.Str::lower($item['nombre']) : $item['tipo'].':'.$item['numero'];
            if (isset($taken[$key])) {
                $other = $items[$taken[$key]];
                $errors[$field][] = match ($item['tipo']) {
                    self::RESULTS => "«{$item['nombre_original']}»: ya hay otro libro de resultados en esta carga («{$other['nombre_original']}»). Cada escenario tiene un solo archivo de datos («{$item['nombre']}»); deja solo uno.",
                    self::REPORT => "«{$item['nombre_original']}»: se guardaría como «{$item['nombre']}», igual que «{$other['nombre_original']}». Cambia el número en el nombre de uno de los dos.",
                    default => "«{$item['nombre_original']}»: el archivo está repetido en esta carga.",
                };

                continue;
            }
            $taken[$key] = $field;
        }

        $latestVersion = collect($existing)->max('version');
        $legacyResults = collect($this->legacyResultsNames($scenarioId))
            ->filter(fn (string $name) => isset($existing[Str::lower($name)]) && $existing[Str::lower($name)]['version'] === $latestVersion)
            ->map(fn (string $name) => $existing[Str::lower($name)]['nombre'])
            ->values()
            ->all();
        $storedDates = $this->storedDates($scenarioId);
        foreach ($items as $field => $item) {
            $current = $existing[Str::lower($item['nombre'])] ?? null;
            // El mismo número con otro formato (informe 1 en .docx ⇒ ahora .pdf) es el mismo archivo.
            $sameNumber = $item['tipo'] === self::DOCUMENT ? null : collect($existing)->first(fn (array $file) => $file['tipo'] === $item['tipo']
                && $file['numero'] === $item['numero']
                && Str::lower($file['nombre']) !== Str::lower($item['nombre']));
            // Archivos que la nueva versión deja de incluir: la misma ruta con otras mayúsculas, el
            // mismo número en otro formato y, para el libro de resultados, los nombres anteriores.
            $supersedes = $current && $current['nombre'] !== $item['nombre'] ? [$current['nombre']] : [];
            if ($sameNumber) {
                $supersedes[] = $sameNumber['nombre'];
            }
            if ($item['tipo'] === self::RESULTS) {
                $supersedes = [...$supersedes, ...$legacyResults];
            }
            $supersedes = array_values(array_unique($supersedes));
            $previous = $current ?? $sameNumber;
            $previousDate = $previous ? ($storedDates[Str::lower($previous['nombre'])] ?? null) : null;
            $items[$field]['reemplaza_a'] = $supersedes;
            $items[$field]['accion'] = match (true) {
                $current !== null && $this->hash($current['path']) === $this->hash($item['file']->getRealPath()) => 'sin_cambios',
                // Un informe solo se actualiza con un archivo más reciente que el guardado.
                $item['tipo'] === self::REPORT && $previous !== null && $item['fecha'] !== null && $previousDate !== null && $item['fecha'] <= $previousDate => 'omitido',
                $current !== null || $supersedes !== [] => 'reemplaza',
                default => 'nuevo',
            };
            if ($items[$field]['accion'] === 'omitido') {
                $items[$field]['advertencias'][] = 'No se cargó: el archivo es del '.date('d/m/Y H:i', $item['fecha']).' y «'.$previous['nombre'].'» guardado es del '.date('d/m/Y H:i', $previousDate).'; solo se actualiza con un archivo más reciente.';
            }
            $items[$field]['etiqueta'] = $item['tipo'] === self::DOCUMENT && $customName && count($files) === 1
                ? $customName
                : $item['nombre'];
        }

        return ['items' => $items, 'errors' => $errors];
    }

    public function hash(string $path): string
    {
        return $this->hashes[$path] ??= (string) hash_file('sha256', $path);
    }
}
