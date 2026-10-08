<?php

namespace App\Http\Controllers;

use App\Models\Escenario;
use App\Models\EscenarioContenido;
use App\Models\Tecnologia;
use App\Models\User;
use App\Services\ScenarioFiles;
use App\Services\SimulationResultsImport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class EscenariosController extends Controller
{
    private string $storagePath = 'app/public';

    private array $rollbackPaths = [];

    public function index()
    {
        return view('app');
    }

    public function data(Request $request)
    {
        $query = Escenario::query()
            ->with([
                'owner:id,name,email',
                'users:id,name,email',
                'contenidos.uploadedBy:id,name',
                'contenidos.modifiedBy:id,name',
                'tecnologias.categoria',
            ])
            ->withCount(['contenidos', 'users'])
            ->latest();

        if ($request->user() && ! $request->user()->hasRole('admin')) {
            $userId = $request->user()->id;
            $query->where(function ($accessible) use ($userId): void {
                $accessible->where('owner_id', $userId)
                    ->orWhereHas('users', fn ($members) => $members->where('users.id', $userId));
            });
        }

        // «N elementos» de la tarjeta = archivos de la versión vigente, no todas las cargas.
        return response()->json(
            $query->get()->each(fn (Escenario $escenario) => $escenario->setAttribute(
                'archivos_vigentes',
                count($this->contentsByVersion($escenario)[$this->versionDirectory($this->currentVersion($escenario))] ?? [])
            ))
        );
    }

    public function detail(Request $request, Escenario $escenario)
    {
        $escenario->load([
            'owner:id,name,email',
            'users:id,name,email',
            'contenidos.uploadedBy:id,name',
            'contenidos.modifiedBy:id,name',
            'tecnologias.categoria',
        ]);
        $byVersion = $this->contentsByVersion($escenario);
        $current = $this->versionDirectory($this->currentVersion($escenario));

        return response()->json([
            ...$escenario->toArray(),
            // Archivos que contiene la versión vigente (nuevos y heredados de la anterior).
            'version_vigente' => $current,
            'contenido_actual' => $byVersion[$current] ?? [],
            // Instantánea real de cada versión, de la más reciente a la más antigua.
            'versiones_contenido' => collect($byVersion)->map(fn (array $files, string $version) => ['version' => $version, 'archivos' => $files])
                ->sortByDesc(fn (array $entry) => (float) $entry['version'])->values(),
        ]);
    }

    /** Versión vigente: la del escenario si su carpeta existe; si no, la carpeta más reciente. */
    private function currentVersion(Escenario $escenario): float
    {
        $root = $this->scenarioRoot($escenario);
        $declared = max(1.0, (float) $escenario->versiones);
        if (File::isDirectory($root.DIRECTORY_SEPARATOR.$this->versionDirectory($declared)) || ! File::isDirectory($root)) {
            return $declared;
        }
        $latest = collect(File::directories($root))->map(fn (string $directory) => basename($directory))->filter(fn (string $name) => is_numeric($name))->map(fn (string $name) => (float) $name)->max();

        return $latest ?: $declared;
    }

    /**
     * Archivos de cada carpeta de versión, enlazados al registro que los cargó: si un archivo
     * viene heredado de una versión anterior, se muestra el registro de esa carga. Así una
     * versión lista exactamente lo que contiene su carpeta (los archivos reemplazados no aparecen).
     *
     * @return array<string, array<int, array<string, mixed>>> versión («1.1») => archivos
     */
    private function contentsByVersion(Escenario $escenario): array
    {
        $root = $this->scenarioRoot($escenario);
        if (! File::isDirectory($root)) {
            return [];
        }
        $rows = $escenario->relationLoaded('contenidos') ? $escenario->contenidos : $escenario->contenidos()->get();
        $rowsByName = $rows->groupBy(fn (EscenarioContenido $row) => Str::lower(basename(str_replace('\\', '/', $row->ruta))));

        $result = [];
        $directories = collect(File::directories($root))
            ->filter(fn (string $directory) => is_numeric(basename($directory)))
            ->sortBy(fn (string $directory) => (float) basename($directory));
        foreach ($directories as $directory) {
            $version = (float) basename($directory);
            $files = [];
            foreach (File::files($directory) as $file) {
                $row = $rowsByName->get(Str::lower($file->getFilename()), collect())
                    ->filter(fn (EscenarioContenido $row) => (float) $row->version <= $version + 1e-9)
                    ->sortBy([fn ($a, $b) => (float) $a->version <=> (float) $b->version, fn ($a, $b) => $a->id <=> $b->id])
                    ->last();
                $files[] = $row
                    ? $row->toArray()
                    : ['id' => null, 'nombre' => $file->getFilename(), 'nombre_original' => null, 'tipo' => 'documento', 'version' => $this->versionDirectory($version), 'tamano' => $file->getSize(), 'updated_at' => null];
            }
            usort($files, fn (array $a, array $b) => strnatcasecmp($a['nombre'], $b['nombre']));
            $result[$this->versionDirectory($version)] = $files;
        }

        return $result;
    }

    public function store(Request $request)
    {
        abort_if($request->user()->hasRole('admin'), 403, 'El administrador supervisa los escenarios, pero no crea contenido operativo.');

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:esceanarios,nombre'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'numero' => ['nullable', 'integer', 'min:1', 'max:'.ScenarioFiles::MAX_SCENARIO_NUMBER, Rule::unique('esceanarios', 'numero')],
            'archivo' => ['required_without:archivos', ...$this->scenarioFileRules()],
            'archivos' => ['required_without:archivo', 'array', 'min:1', 'max:10'],
            'archivos.*' => $this->scenarioFileRules(),
            ...$this->dateRules(),
            ...$this->technologyRules(),
        ], $this->technologyMessages() + $this->numberMessages());

        $technologyIds = $this->technologyIds($validated['tecnologias'] ?? []);
        $files = $this->uploadedScenarioFilesByField($request);
        $imports = $this->readImports($files);
        // Sin número indicado: el que trae el nombre del libro de resultados si está libre, o el siguiente disponible.
        $validated['numero'] = (int) ($validated['numero'] ?? ScenarioFiles::nextScenarioNumber($this->scenarioNumberHint($files, $imports)));
        $items = $this->planFiles($files, $imports, $validated['numero'], dates: $this->uploadedDates($request, $files));

        try {
            return $this->createScenario($validated, $items, $technologyIds);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['numero' => "El escenario número {$validated['numero']} ya existe; elige otro número."]);
        }
    }

    private function createScenario(array $validated, array $items, array $technologyIds = [])
    {
        $escenario = $this->withScenarioFiles(function () use ($validated, $items, $technologyIds) {
            $user = Auth::user();
            $escenario = Escenario::create([
                'numero' => $validated['numero'],
                'nombre' => $validated['nombre'],
                'owner_id' => $user->id,
                'estado' => 'Activo',
                'descripcion' => $validated['descripcion'],
                'versiones' => $items ? 1 : 0,
            ]);
            $escenario->update([
                'storage_directory' => $this->scenarioDirectory($escenario),
            ]);

            $escenario->users()->attach($user->id, [
                'access_level' => 'owner',
                'invited_by' => $user->id,
            ]);
            $escenario->tecnologias()->sync($technologyIds);

            $this->rollbackPaths[] = $this->scenarioRoot($escenario);
            File::ensureDirectoryExists($this->scenarioRoot($escenario));
            $first = true;
            foreach ($items as $item) {
                $stored = $this->storeScenarioFile($escenario, $item['file'], $item['nombre'], $first ? 1.0 : null, $item['reemplaza_a']);
                $first = false;
                if ($stored['stored']) {
                    $this->createContent($escenario->id, $user->id, $item, $stored);
                }
                if ($stored['stored'] && $item['import']) {
                    $this->persistSimulation($escenario->id, $stored['path'], $item['import']);
                }
                if ($stored['version'] !== (float) $escenario->versiones) {
                    $escenario->update(['versiones' => $stored['version']]);
                }
            }

            return $escenario;
        });

        return response()->json([
            'message' => $this->uploadSummary($items, 'Escenario '.$escenario->numero.' creado exitosamente.'),
            'archivos' => $this->filesSummary($items),
            'data' => $escenario->load(['owner:id,name,email', 'contenidos', 'tecnologias.categoria']),
        ], 201);
    }

    /**
     * Reconoce los archivos antes de guardarlos (arrastrar y soltar en «Nuevo escenario» y
     * «Agregar archivos»): qué es cada uno, si su estructura es válida, el número que trae en
     * el nombre y lo que ya existe en el escenario. No guarda nada. La vista previa calcula
     * con esto los nombres finales al marcar o desmarcar archivos; al guardar, el servidor
     * vuelve a validar todo.
     */
    public function analyzeFiles(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'archivos' => ['required', 'array', 'min:1', 'max:10'],
            'escenario_id' => ['nullable', 'integer', Rule::exists('esceanarios', 'id')],
        ], [
            'archivos.required' => 'Selecciona al menos un archivo.',
            'archivos.max' => 'Puedes cargar hasta 10 archivos a la vez.',
        ]);

        $escenario = $request->filled('escenario_id') ? Escenario::query()->findOrFail($request->integer('escenario_id')) : null;
        if ($escenario) {
            abort_unless($escenario->owner_id === $user->id && $user->can('escenarios.versionar'), 403, 'Solo el owner del escenario puede agregarle archivos.');
        } else {
            abort_if($user->hasRole('admin'), 403, 'El administrador supervisa los escenarios, pero no crea contenido operativo.');
            abort_unless($user->can('escenarios.crear'), 403, 'No tienes permiso para crear escenarios.');
        }

        $naming = new ScenarioFiles;
        $root = $escenario ? $this->scenarioRoot($escenario) : null;
        $reports = $naming->reportsIn($naming->existingFiles($root));
        $rows = [];

        foreach ((array) $request->file('archivos', []) as $index => $file) {
            $original = $file instanceof UploadedFile ? basename($file->getClientOriginalName()) : 'archivo '.($index + 1);
            $row = [
                'indice' => $index,
                'campo' => 'archivos.'.$index,
                'nombre_original' => $original,
                'extension' => strtolower(pathinfo($original, PATHINFO_EXTENSION)),
                'tamano' => $file instanceof UploadedFile ? ($file->getSize() ?: 0) : 0,
                'sha256' => null,
                'tipo' => null,
                'valido' => false,
                'errores' => [],
                'resultados' => null,
                'numero_escenario_en_nombre' => null,
                'numero_informe' => null,
                'origen_numero' => null,
            ];
            if (! $file instanceof UploadedFile) {
                $row['errores'][] = "«{$original}»: no fue posible leer el archivo.";
                $rows[] = $row;

                continue;
            }

            $validator = Validator::make(['archivo' => $file], ['archivo' => $this->scenarioFileRules()], [
                'archivo.mimes' => 'solo se admiten archivos Word (.doc, .docx), PDF o Excel (.xlsx, .xls).',
                'archivo.extensions' => 'solo se admiten archivos Word (.doc, .docx), PDF o Excel (.xlsx, .xls).',
                'archivo.max' => 'supera el máximo de 50 MB.',
            ]);
            if ($validator->fails()) {
                $row['errores'] = array_map(fn (string $message) => "«{$original}»: {$message}", array_values(array_unique($validator->errors()->all())));
                $rows[] = $row;

                continue;
            }

            $row['sha256'] = $naming->hash($file->getRealPath());
            try {
                $import = $this->readImport($file, $row['campo']);
            } catch (ValidationException $exception) {
                // Tiene las hojas de un libro de resultados, pero no cumple el formato.
                $row['tipo'] = ScenarioFiles::RESULTS;
                $row['errores'] = collect($exception->errors())->flatten()->values()->all();
                $rows[] = $row;

                continue;
            }

            $row['tipo'] = ScenarioFiles::kind($file, $import);
            $row['valido'] = true;
            if ($import) {
                $row['resultados'] = [
                    'formato' => $import['formato'],
                    'registros' => $import['registros'],
                    'columnas' => $import['columnas'],
                ];
                $row['numero_escenario_en_nombre'] = ScenarioFiles::scenarioNumberFromName($original);
            } elseif ($row['tipo'] === ScenarioFiles::REPORT) {
                [$row['numero_informe'], $row['origen_numero']] = $naming->fixedReportNumber($file, $reports, $escenario?->id);
            } elseif ($reserved = $naming->reservedNameError($original)) {
                $row['valido'] = false;
                $row['errores'][] = $reserved;
            }
            $rows[] = $row;
        }

        $scenarioFile = collect($rows)->first(fn (array $row) => $row['valido'] && $row['tipo'] === ScenarioFiles::RESULTS);
        $suggested = $escenario
            ? $this->ensureScenarioNumber($escenario)
            : ScenarioFiles::nextScenarioNumber($scenarioFile['numero_escenario_en_nombre'] ?? null);

        return response()->json([
            'escenario' => [
                'id' => $escenario?->id,
                'nombre' => $escenario?->nombre,
                'numero' => $escenario?->numero,
                'numero_sugerido' => $suggested,
                'nombre_sugerido' => 'Escenario '.$suggested,
                'numeros_ocupados' => ScenarioFiles::usedScenarioNumbers(),
            ],
            'archivo_escenario' => $scenarioFile['indice'] ?? null,
            'existentes' => $naming->existingSummary($root, $escenario?->id),
            'archivos' => $rows,
        ]);
    }

    public function show(Escenario $escenario)
    {
        return view('app');
    }

    public function invite(Request $request, Escenario $escenario)
    {
        $actor = $request->user();

        abort_unless($escenario->owner_id === $actor->id, 403, 'Solo el owner del escenario puede gestionar sus accesos.');

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'access_level' => ['required', 'in:supervisor,editor'],
        ]);

        $member = User::where('email', $validated['email'])->firstOrFail();
        abort_if($member->id === $escenario->owner_id, 422, 'El owner ya tiene acceso total al escenario.');
        abort_if($member->hasRole('admin'), 422, 'El administrador ya supervisa todos los escenarios; no necesita invitación.');
        abort_unless($member->is_active, 422, 'La cuenta está deshabilitada.');
        abort_unless($member->can('escenarios.leer'), 422, 'La cuenta no tiene habilitado el módulo de Escenarios.');

        $escenario->users()->syncWithoutDetaching([
            $member->id => [
                'access_level' => $validated['access_level'],
                'invited_by' => $actor->id,
            ],
        ]);

        DB::table('escenarios_users')
            ->where('escenario_id', $escenario->id)
            ->where('user_id', $member->id)
            ->update([
                'access_level' => $validated['access_level'],
                'invited_by' => $actor->id,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Acceso al escenario actualizado exitosamente.',
            'member' => [
                ...$member->only(['id', 'name', 'email']),
                'access_level' => $validated['access_level'],
            ],
        ]);
    }

    public function removeMember(Request $request, Escenario $escenario, User $user)
    {
        abort_unless($escenario->owner_id === $request->user()->id, 403, 'Solo el owner del escenario puede gestionar sus accesos.');
        abort_if($user->id === $escenario->owner_id, 422, 'No es posible retirar al owner del escenario.');

        $removed = $escenario->users()->where('users.id', $user->id)->exists();
        abort_unless($removed, 404, 'El usuario no tiene acceso a este escenario.');
        $escenario->users()->detach($user->id);

        return response()->json(['message' => 'El acceso del usuario fue retirado. Su cuenta permanece activa.']);
    }

    public function uploadContent(Request $request, Escenario $escenario)
    {
        $user = $request->user();
        $hasAccess = $escenario->owner_id === $user->id
            || $escenario->users()->where('users.id', $user->id)->exists();
        abort_unless($hasAccess, 403, 'No tienes acceso a este escenario.');

        $validated = $request->validate([
            'nombre' => ['nullable', 'string', 'max:255'],
            'archivo' => ['required_without:archivos', ...$this->scenarioFileRules()],
            'archivos' => ['required_without:archivo', 'array', 'min:1', 'max:10'],
            'archivos.*' => $this->scenarioFileRules(),
            ...$this->dateRules(),
        ]);

        $files = $this->uploadedScenarioFilesByField($request);
        $imports = $this->readImports($files);
        $scenarioNumber = $this->ensureScenarioNumber($escenario);
        $customName = $validated['nombre'] ?? null;
        $dates = $this->uploadedDates($request, $files);
        $result = $this->withScenarioFiles(function () use ($escenario, $user, $files, $imports, $scenarioNumber, $customName, $dates) {
            $lockedScenario = Escenario::query()->lockForUpdate()->findOrFail($escenario->id);
            // Los nombres se calculan con el escenario bloqueado: dos cargas simultáneas no toman el mismo número de informe.
            $items = $this->planFiles($files, $imports, $scenarioNumber, $lockedScenario, $customName, $dates);
            $contents = [];
            $storedCount = 0;
            $newVersion = false;

            foreach ($items as $item) {
                if ($item['accion'] === 'omitido') {
                    continue; // Informe igual o más antiguo que el guardado: no se carga.
                }
                $stored = $this->storeScenarioFile($lockedScenario, $item['file'], $item['nombre'], null, $item['reemplaza_a']);
                $content = $stored['stored']
                    ? $this->createContent($escenario->id, $user?->id, $item, $stored)
                    : EscenarioContenido::query()
                        ->where('escenario_id', $escenario->id)
                        ->where('ruta', $stored['path'])
                        ->latest()
                        ->first();

                if ($item['import'] && $stored['stored']) {
                    $this->persistSimulation($escenario->id, $stored['path'], $item['import']);
                }

                if ($stored['stored']) {
                    $storedCount++;
                    $newVersion = $newVersion || $stored['new_version'];
                    $lockedScenario->update([
                        'versiones' => $stored['version'],
                        'estado' => 'Activo',
                    ]);
                }
                if ($content) {
                    $contents[] = $content;
                }
            }

            return [
                'contents' => $contents,
                'new_version' => $newVersion,
                'stored_count' => $storedCount,
                'items' => $items,
            ];
        });
        $items = $result['items'];

        $loadedContents = collect($result['contents'])
            ->map->load(['uploadedBy:id,name', 'modifiedBy:id,name'])
            ->values();

        return response()->json([
            'message' => $result['stored_count'] === 0
                ? 'Los archivos no presentan cambios; no se almacenaron copias duplicadas.'.$this->skippedNote($items)
                : $this->uploadSummary($items, 'Archivos almacenados exitosamente.'),
            'stored' => $result['stored_count'] > 0,
            'new_version' => $result['new_version'],
            'archivos' => $this->filesSummary($items),
            'data' => count($files) === 1 ? $loadedContents->first() : $loadedContents,
        ], $result['stored_count'] > 0 ? 201 : 200);
    }

    public function update(Request $request, Escenario $escenario)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            $validated = $request->validate([
                'nombre' => ['required', 'string', 'max:255', 'unique:esceanarios,nombre,'.$escenario->id],
            ]);
            $escenario->update(['nombre' => $validated['nombre']]);

            return response()->json([
                'message' => 'Nombre del escenario corregido exitosamente.',
                'data' => $escenario->fresh()->load(['owner:id,name,email', 'tecnologias.categoria']),
            ]);
        }

        abort_unless(
            $escenario->owner_id === $user->id
                || $escenario->users()->where('users.id', $user->id)->exists(),
            403,
            'No tienes acceso a este escenario.'
        );

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:esceanarios,nombre,'.$escenario->id],
            'descripcion' => ['required', 'string', 'max:2000'],
            'estado' => ['required', 'in:Activo,Inactivo'],
            'archivo' => ['nullable', ...$this->scenarioFileRules()],
            ...$this->dateRules(),
            ...$this->technologyRules(),
        ], $this->technologyMessages());

        // Solo se modifican las tecnologías si el formulario envía el campo (vacío = quitar todas).
        $syncTechnologies = $request->exists('tecnologias');
        $technologyIds = $syncTechnologies ? $this->technologyIds($validated['tecnologias'] ?? [], $escenario) : [];

        abort_if(
            $request->hasFile('archivo')
                && $request->attributes->get('scenario_access_level') !== 'owner',
            403,
            'Tu rol permite editar el escenario, pero no reemplazar archivos ni crear versiones.'
        );

        $files = $request->hasFile('archivo') ? ['archivo' => $request->file('archivo')] : [];
        $imports = $this->readImports($files);
        $scenarioNumber = $files ? $this->ensureScenarioNumber($escenario) : null;
        $dates = $this->uploadedDates($request, $files);
        $items = [];

        $newVersion = $this->withScenarioFiles(function () use ($validated, $escenario, $user, $files, $imports, $scenarioNumber, $dates, &$items, $syncTechnologies, $technologyIds) {
            $lockedScenario = Escenario::query()->lockForUpdate()->findOrFail($escenario->id);
            $items = $files ? $this->planFiles($files, $imports, $scenarioNumber, $lockedScenario, null, $dates) : [];
            $item = ($items['archivo']['accion'] ?? null) === 'omitido' ? null : ($items['archivo'] ?? null);
            $scenarioData = [
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'],
                'estado' => $validated['estado'],
            ];

            if ($item) {
                $stored = $this->storeScenarioFile($lockedScenario, $item['file'], $item['nombre'], null, $item['reemplaza_a']);

                if ($stored['stored']) {
                    $this->createContent($escenario->id, $user->id, $item, $stored);
                    $scenarioData['versiones'] = $stored['version'];
                }
                if ($stored['stored'] && $item['import']) {
                    $this->persistSimulation($escenario->id, $stored['path'], $item['import']);
                }
            }
            $lockedScenario->update($scenarioData);
            if ($syncTechnologies) {
                $lockedScenario->tecnologias()->sync($technologyIds);
            }

            return $stored ?? null;
        });

        $item = $items['archivo'] ?? null;

        return response()->json([
            'message' => $item && $item['accion'] === 'omitido'
                ? 'Escenario actualizado; el informe no se cargó.'.$this->skippedNote($items)
                : ($item
                ? (! $newVersion['stored']
                    ? 'Escenario actualizado; el archivo no cambió y no se almacenó nuevamente.'
                    : ($newVersion['new_version']
                        ? 'Escenario actualizado y nueva versión registrada exitosamente.'
                        : 'Escenario actualizado; el archivo fue agregado a la versión actual.')).$this->renameNote($items)
                : 'Escenario actualizado exitosamente.'),
            'archivos' => $this->filesSummary($items),
            'data' => $escenario->fresh()->load(['owner:id,name,email', 'contenidos.uploadedBy:id,name', 'contenidos.modifiedBy:id,name', 'tecnologias.categoria']),
        ]);
    }

    public function destroy(Request $request, Escenario $escenario)
    {
        abort_unless(
            $request->user()->hasRole('admin') || $escenario->owner_id === $request->user()->id,
            403,
            'Solo el owner o un administrador puede eliminar el escenario.'
        );

        $directory = $this->scenarioRoot($escenario);
        $escenario->users()->detach();
        $escenario->delete();
        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }

        return response()->json(['message' => 'Escenario eliminado exitosamente.']);
    }

    public function downloadContent(Request $request, Escenario $escenario, EscenarioContenido $contenido): BinaryFileResponse
    {
        $this->authorizeReading($request, $escenario);
        abort_unless($contenido->escenario_id === $escenario->id, 404);

        $path = storage_path('app'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $contenido->ruta));
        abort_unless(File::isFile($path), 404, 'El archivo ya no está disponible.');

        return response()->download($path, basename($contenido->nombre));
    }

    public function download(Request $request, Escenario $escenario): BinaryFileResponse
    {
        $this->authorizeReading($request, $escenario);
        $root = $this->scenarioRoot($escenario);
        abort_unless(File::isDirectory($root), 404, 'El escenario no contiene archivos descargables.');

        $temporary = tempnam(sys_get_temp_dir(), 'scenario-');
        $zip = new ZipArchive;
        abort_unless($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'No fue posible preparar la descarga.');

        foreach (File::allFiles($root) as $file) {
            $zip->addFile($file->getRealPath(), str_replace('\\', '/', $file->getRelativePathname()));
        }
        $zip->close();

        return response()->download($temporary, Str::slug($escenario->nombre).'.zip')->deleteFileAfterSend(true);
    }

    private function readImport(?\Illuminate\Http\UploadedFile $file, string $field = 'archivo'): ?array
    {
        return app(SimulationResultsImport::class)->read($file, $field);
    }

    /**
     * Lee todos los archivos de la solicitud y reúne los errores de formato de
     * cada libro de resultados antes de responder, para corregirlos de una vez.
     *
     * @param  array<string, UploadedFile>  $files  campo => archivo
     * @return array<string, array|null> campo => datos importables (null si no es un libro de resultados)
     */
    private function readImports(array $files): array
    {
        $imports = [];
        $errors = [];
        foreach ($files as $field => $file) {
            try {
                $imports[$field] = $this->readImport($file, $field);
            } catch (ValidationException $exception) {
                $errors = array_merge_recursive($errors, $exception->errors());
                $imports[$field] = null;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $imports;
    }

    private function technologyRules(): array
    {
        return [
            'tecnologias' => ['nullable', 'array', 'max:50'],
            'tecnologias.*' => ['required', 'string', 'distinct', Rule::exists('tecnologias', 'codigo')],
        ];
    }

    private function technologyMessages(): array
    {
        return [
            'tecnologias.array' => 'Selecciona las tecnologías desde el catálogo.',
            'tecnologias.*.exists' => 'La tecnología «:input» no pertenece al catálogo.',
            'tecnologias.*.distinct' => 'La tecnología «:input» está repetida.',
            'tecnologias.*.string' => 'Selecciona las tecnologías desde el catálogo.',
            'tecnologias.*.required' => 'Selecciona las tecnologías desde el catálogo.',
        ];
    }

    /**
     * Convierte códigos del catálogo en ids. Una tecnología desactivada solo se
     * acepta si el escenario ya la tenía asociada (no se pierde al editar).
     *
     * @param  array<int, string>  $codes
     * @return array<int, int>
     */
    private function technologyIds(array $codes, ?Escenario $escenario = null): array
    {
        if (! $codes) {
            return [];
        }
        $technologies = Tecnologia::query()->whereIn('codigo', $codes)->get(['id', 'codigo', 'activo']);
        $current = $escenario ? $escenario->tecnologias()->pluck('tecnologias.id')->all() : [];
        $unavailable = $technologies
            ->filter(fn (Tecnologia $technology) => ! $technology->activo && ! in_array($technology->id, $current, true))
            ->pluck('codigo');
        if ($unavailable->isNotEmpty()) {
            throw ValidationException::withMessages([
                'tecnologias' => 'La tecnología «'.$unavailable->implode('», «').'» está desactivada en el catálogo.',
            ]);
        }

        return $technologies->pluck('id')->all();
    }

    private function scenarioFileRules(): array
    {
        $extensions = implode(',', ScenarioFiles::ALLOWED_EXTENSIONS);

        return ['file', 'max:51200', 'mimes:'.$extensions, 'extensions:'.$extensions];
    }

    /** @return array<string, \Illuminate\Http\UploadedFile> campo del formulario => archivo */
    private function uploadedScenarioFilesByField(Request $request): array
    {
        $files = [];
        foreach ((array) $request->file('archivos', []) as $index => $file) {
            if ($file) {
                $files['archivos.'.$index] = $file;
            }
        }
        if ($request->hasFile('archivo')) {
            $files['archivo'] = $request->file('archivo');
        }

        return $files;
    }

    /**
     * Resume lo almacenado (los archivos sin cambios no cuentan) y los nombres asignados.
     *
     * @param  array<string, array<string, mixed>>  $items
     */
    private function uploadSummary(array $items, string $prefix): string
    {
        $stored = collect($items)->whereNotIn('accion', ['sin_cambios', 'omitido']);
        $count = fn (string $kind) => $stored->where('tipo', $kind)->count();
        $parts = [];
        if ($imported = $count(ScenarioFiles::RESULTS)) {
            $parts[] = $imported.' '.($imported === 1 ? 'documento fue importado a resultados' : 'documentos fueron importados a resultados');
        }
        if ($reports = $count(ScenarioFiles::REPORT)) {
            $parts[] = $reports.' '.($reports === 1 ? 'informe quedó disponible' : 'informes quedaron disponibles');
        }
        if ($documents = $count(ScenarioFiles::DOCUMENT)) {
            $parts[] = $documents.' '.($documents === 1 ? 'archivo quedó disponible para descarga' : 'archivos quedaron disponibles para descarga');
        }

        $last = array_pop($parts);
        $text = $parts ? implode(', ', $parts).' y '.$last : $last;

        return ($text ? $prefix.' '.ucfirst($text).'.' : $prefix).$this->renameNote($items).$this->skippedNote($items);
    }

    /** « No se cargó «x»: …» para los informes iguales o más antiguos que los guardados. */
    private function skippedNote(array $items): string
    {
        return collect($items)
            ->where('accion', 'omitido')
            ->map(fn (array $item) => ' «'.$item['nombre_original'].'»: '.lcfirst(end($item['advertencias'])))
            ->implode('');
    }

    /**
     * Fechas de los archivos (File.lastModified del navegador, en ms): `fechas[i]` para
     * `archivos[i]` y `fecha_archivo` para `archivo`.
     *
     * @return array<string, int|null> campo => segundos
     */
    private function uploadedDates(Request $request, array $files): array
    {
        $dates = [];
        foreach (array_keys($files) as $field) {
            $value = $field === 'archivo' ? $request->input('fecha_archivo') : $request->input('fechas.'.substr($field, strlen('archivos.')));
            $dates[$field] = ScenarioFiles::normalizeDate($value);
        }

        return $dates;
    }

    private function dateRules(): array
    {
        return [
            'fechas' => ['nullable', 'array', 'max:10'],
            'fechas.*' => ['nullable', 'integer', 'min:0'],
            'fecha_archivo' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** « Se guardó «x» como «y».» para los archivos cuyo nombre se normalizó. */
    private function renameNote(array $items): string
    {
        $renamed = collect($items)
            ->filter(fn (array $item) => ! in_array($item['accion'], ['sin_cambios', 'omitido'], true) && $item['nombre'] !== $item['nombre_original'])
            ->map(fn (array $item) => '«'.$item['nombre_original'].'» como «'.$item['nombre'].'»'.($item['accion'] === 'reemplaza' ? ' (reemplaza la versión anterior)' : ''));

        return $renamed->isEmpty() ? '' : ' Se guardó '.$renamed->implode('; ').'.';
    }

    /** @return array<int, array<string, mixed>> nombre original => nombre guardado, por archivo */
    private function filesSummary(array $items): array
    {
        return collect($items)->map(fn (array $item) => [
            'campo' => $item['campo'],
            'nombre_original' => $item['nombre_original'],
            'nombre' => $item['nombre'],
            'tipo' => $item['tipo'],
            'numero' => $item['numero'],
            'accion' => $item['accion'],
            'advertencias' => $item['advertencias'],
        ])->values()->all();
    }

    /**
     * Asigna el nombre canónico de cada archivo; responde 422 con todos los conflictos juntos.
     *
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, array|null>  $imports
     * @return array<string, array<string, mixed>>
     */
    private function planFiles(array $files, array $imports, int $scenarioNumber, ?Escenario $escenario = null, ?string $customName = null, array $dates = []): array
    {
        $kinds = [];
        foreach ($files as $field => $file) {
            $kinds[$field] = ScenarioFiles::kind($file, $imports[$field] ?? null);
        }
        $plan = (new ScenarioFiles)->plan(
            $files,
            $kinds,
            $scenarioNumber,
            $escenario ? $this->scenarioRoot($escenario) : null,
            $escenario?->id,
            $customName,
            $dates
        );
        if ($plan['errors']) {
            throw ValidationException::withMessages($plan['errors']);
        }

        return collect($plan['items'])->map(fn (array $item, string $field) => $item + ['import' => $imports[$field] ?? null])->all();
    }

    /** @param array{path: string, version: float} $stored */
    private function createContent(int $scenarioId, ?int $userId, array $item, array $stored): EscenarioContenido
    {
        return EscenarioContenido::create([
            'escenario_id' => $scenarioId,
            'uploaded_by' => $userId,
            'modified_by' => $userId,
            'nombre' => $item['etiqueta'],
            'nombre_original' => $item['nombre_original'],
            'ruta' => $stored['path'],
            'tipo' => ScenarioFiles::CONTENT_TYPES[$item['tipo']],
            'mime_type' => $item['file']->getClientMimeType(),
            'tamano' => $item['file']->getSize() ?: 0,
            'fecha_archivo' => $item['fecha'] ? date('Y-m-d H:i:s', $item['fecha']) : null,
            'version' => $stored['version'],
            'estado' => 'Disponible',
        ]);
    }

    /** Número sugerido por el nombre del libro de resultados («resultados escenario 2.xlsx» ⇒ 2). */
    private function scenarioNumberHint(array $files, array $imports): ?int
    {
        foreach ($files as $field => $file) {
            if (($imports[$field] ?? null) !== null) {
                return ScenarioFiles::scenarioNumberFromName($file->getClientOriginalName());
            }
        }

        return null;
    }

    /** Escenarios creados antes del número: se les asigna uno al primer uso (la migración ya lo hace). */
    private function ensureScenarioNumber(Escenario $escenario): int
    {
        if (! $escenario->numero) {
            $escenario->forceFill([
                'numero' => ScenarioFiles::nextScenarioNumber(ScenarioFiles::scenarioNumberFromName($escenario->nombre, false)),
            ])->save();
        }

        return (int) $escenario->numero;
    }

    private function numberMessages(): array
    {
        return [
            'numero.integer' => 'El número del escenario debe ser un número entero.',
            'numero.min' => 'El número del escenario debe ser mayor que cero.',
            'numero.max' => 'El número del escenario no puede superar :max.',
            'numero.unique' => 'Ya existe un escenario con el número :input.',
        ];
    }

    private function persistSimulation(int $scenarioId, string $path, ?array $import): void
    {
        if (! $import) {
            return;
        }
        $content = EscenarioContenido::where('escenario_id', $scenarioId)->where('ruta', $path)->latest('id')->firstOrFail();
        app(SimulationResultsImport::class)->persist($scenarioId, $content->id, $import);
        app(\App\Services\DataVersions::class)->register($scenarioId, $content->id, SimulationResultsImport::VERSION_TYPE, $import['rows']);
    }

    /** Columna de resultados_simulacion => clave expuesta a la vista de resultados. */
    public const SIMULATION_RESULT_FIELDS = [
        'caudal' => 'caudal_m3_h',
        'radiacion_solar' => 'irradiancia_w_m2',
        'temperatura' => 'temperatura_c',
        'velocidad_viento' => 'velocidad_viento_m_s',
        'potencia_solar' => 'potencia_solar_w',
        'potencia_neta' => 'potencia_neta_w',
        'consumo_planta' => 'potencia_consumida_planta_w',
        // El informe del Escenario 1 confirma que la columna rotulada «(W)» está en Wh.
        'energia_almacenada' => 'energia_almacenada_wh',
        'agua_desalinizada' => 'agua_desalinizada_acum_m3',
        'salmuera' => 'salmuera_acum_m3',
        'lodos_gruesos' => 'lodos_gruesos_acum_paquetes',
        'lodos_finos' => 'lodos_finos_acum_paquetes',
        'estado_carga' => 'estado_carga_pct',
        'excedente_no_aprovechado' => 'excedente_no_aprovechado_acum_wh',
        'energia_diesel' => 'energia_diesel_acum_wh',
        'combustible_diesel' => 'combustible_diesel_acum_l',
        'demanda_no_cubierta' => 'demanda_no_cubierta_acum_wh',
    ];

    public function simulationResults(Request $request, Escenario $escenario)
    {
        $mapping = self::SIMULATION_RESULT_FIELDS;
        $versions = DB::table('data_versions')
            ->where('escenario_id', $escenario->id)
            ->where('tipo', SimulationResultsImport::VERSION_TYPE)
            ->orderByDesc('revision')
            ->get();
        $files = $escenario->contenidos()->whereIn('id', $versions->pluck('archivo_id'))->get()->keyBy('id');
        $groups = DB::table(SimulationResultsImport::TABLE)
            ->whereIn('archivo_id', $versions->pluck('archivo_id'))
            ->orderBy('tiempo_minutos')
            ->get()
            ->groupBy('archivo_id');

        $items = $versions->map(function ($version, $index) use ($files, $groups, $mapping) {
            $file = $files->get($version->archivo_id);
            $samples = ['1' => [], '5' => [], '10' => [], '60' => []];
            $available = [];
            foreach ($groups->get($version->archivo_id, collect()) as $row) {
                $point = ['tiempo_minutos' => (int) $row->tiempo_minutos];
                foreach ($mapping as $source => $target) {
                    $value = $row->$source ?? null;
                    $point[$target] = $value === null ? null : (float) $value;
                    if ($value !== null) {
                        $available[$target] = true;
                    }
                }
                // Clave histórica: algunos clientes leían el valor sin convertir.
                $point['energia_almacenada_original'] = $point['energia_almacenada_wh'];
                $samples[(string) $row->intervalo_minutos][] = $point;
            }

            return [
                'id' => $version->archivo_id,
                'data_version_id' => $version->id,
                'nombre' => $file?->nombre ?? 'Documento no disponible',
                'version_datos' => $version->version,
                'revision' => (int) $version->revision,
                'es_actual' => $index === 0,
                'publicada_en' => $version->created_at,
                'variables_disponibles' => array_values(array_filter($mapping, fn ($target) => isset($available[$target]))),
                'muestreos' => $samples,
                'advertencias' => [],
            ];
        })->values();

        return response()->json([
            'version_actual' => $items->first()['version_datos'] ?? null,
            'versiones' => $items->map(fn ($item) => collect($item)->except(['muestreos', 'advertencias'])->all()),
            // Conserva la clave histórica para clientes que ya consumen este endpoint.
            'archivos' => $items,
        ]);
    }

    private function authorizeReading(Request $request, Escenario $escenario): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole('admin')
                || $escenario->owner_id === $user->id
                || $escenario->users()->where('users.id', $user->id)->exists(),
            403,
            'No tienes acceso a este escenario.'
        );
    }

    /**
     * @param  array<int, string>  $supersedes  archivos de la versión actual que la nueva versión deja de incluir
     *                                          (el libro de resultados con su nombre anterior)
     * @return array{path: string, version: float, new_version: bool, stored: bool}
     */
    private function storeScenarioFile(Escenario $escenario, object $archivo, string $fileName, ?float $initialVersion = null, array $supersedes = []): array
    {
        // Compare with the latest file only: restoring an older result is a new variation.
        // $fileName es el nombre canónico (ScenarioFiles), no el nombre con que se subió.
        $scenarioRoot = $this->scenarioRoot($escenario);
        $currentVersion = $initialVersion ?? (float) $escenario->versiones;
        $currentVersion = $currentVersion < 1 ? 1.0 : $currentVersion;
        $currentDirectory = $scenarioRoot.DIRECTORY_SEPARATOR.$this->versionDirectory($currentVersion);
        $fileName = basename($fileName);
        $currentFile = $currentDirectory.DIRECTORY_SEPARATOR.$fileName;
        $newVersion = false;
        $latestExistingFile = $this->latestVersionedFile($scenarioRoot, $fileName);

        if ($latestExistingFile !== null
            && hash_file('sha256', $latestExistingFile['path']) === hash_file('sha256', $archivo->getRealPath())) {
            return [
                'path' => 'public/'.str_replace('\\', '/', $this->relativeScenarioPath(
                    $escenario,
                    $latestExistingFile['version'],
                    $latestExistingFile['name']
                )),
                'version' => $currentVersion,
                'new_version' => false,
                'stored' => false,
            ];
        }

        $supersedes = array_values(array_filter(
            array_map('basename', $supersedes),
            fn (string $name) => File::isFile($currentDirectory.DIRECTORY_SEPARATOR.$name)
        ));

        // Contenido distinto, o un archivo anterior que este reemplaza ⇒ nueva versión acumulativa.
        if ($latestExistingFile !== null || $supersedes !== []) {
            $nextVersion = $this->nextVersionValue($currentVersion);
            $nextDirectory = $scenarioRoot.DIRECTORY_SEPARATOR.$this->versionDirectory($nextVersion);

            if (File::exists($nextDirectory)) {
                throw new \RuntimeException('La siguiente versión ya existe en almacenamiento.');
            }
            $this->rollbackPaths[] = $nextDirectory;

            File::ensureDirectoryExists($scenarioRoot);
            if (! File::copyDirectory($currentDirectory, $nextDirectory)) {
                throw new \RuntimeException('No fue posible copiar la versión actual del escenario.');
            }

            foreach ($supersedes as $name) {
                File::delete($nextDirectory.DIRECTORY_SEPARATOR.$name);
            }

            $currentVersion = $nextVersion;
            $currentDirectory = $nextDirectory;
            $currentFile = $currentDirectory.DIRECTORY_SEPARATOR.$fileName;
            $newVersion = true;
        }

        File::ensureDirectoryExists($currentDirectory);
        if (! $newVersion) {
            $this->rollbackPaths[] = $currentFile;
        }
        if (! File::copy($archivo->getRealPath(), $currentFile)) {
            throw new \RuntimeException('No fue posible almacenar el archivo del escenario.');
        }

        return [
            'path' => 'public/'.str_replace('\\', '/', $this->relativeScenarioPath($escenario, $currentVersion, $fileName)),
            'version' => $currentVersion,
            'new_version' => $newVersion,
            'stored' => true,
        ];
    }

    /**
     * Última copia del archivo; la comparación ignora mayúsculas para que Linux (Hostinger)
     * y Windows (XAMPP) reconozcan el mismo archivo.
     *
     * @return array{path: string, version: float, name: string}|null
     */
    private function latestVersionedFile(string $scenarioRoot, string $fileName): ?array
    {
        if (! File::isDirectory($scenarioRoot)) {
            return null;
        }

        $versionDirectories = collect(File::directories($scenarioRoot))
            ->filter(fn (string $directory) => is_numeric(basename($directory)))
            ->sortByDesc(fn (string $directory) => (float) basename($directory));

        $wanted = Str::lower($fileName);
        foreach ($versionDirectories as $directory) {
            $match = null;
            foreach (File::files($directory) as $file) {
                if ($file->getFilename() === $fileName) {
                    $match = $file;
                    break;
                }
                if ($match === null && Str::lower($file->getFilename()) === $wanted) {
                    $match = $file;
                }
            }

            if ($match !== null) {
                return [
                    'path' => $match->getPathname(),
                    'version' => (float) basename($directory),
                    'name' => $match->getFilename(),
                ];
            }
        }

        return null;
    }

    private function scenarioRoot(Escenario $escenario): string
    {
        $scenariosRoot = storage_path($this->storagePath.DIRECTORY_SEPARATOR.'escenarios');

        return $scenariosRoot.DIRECTORY_SEPARATOR.($escenario->storage_directory ?: $this->scenarioDirectory($escenario));
    }

    private function scenarioDirectory(Escenario $escenario): string
    {
        return $escenario->id.'-'.(Str::slug($escenario->getOriginal('nombre') ?: $escenario->nombre) ?: 'escenario');
    }

    private function relativeScenarioPath(Escenario $escenario, float $version, string $fileName): string
    {
        return 'escenarios'.DIRECTORY_SEPARATOR.basename($this->scenarioRoot($escenario))
            .DIRECTORY_SEPARATOR.$this->versionDirectory($version).DIRECTORY_SEPARATOR.$fileName;
    }

    private function versionDirectory(float $version): string
    {
        return number_format($version, 1, '.', '');
    }

    private function nextVersionValue(float $currentVersion): float
    {
        return round($currentVersion + 0.1, 1);
    }

    private function withScenarioFiles(callable $callback): mixed
    {
        $this->rollbackPaths = [];
        try {
            return DB::transaction($callback);
        } catch (\Throwable $exception) {
            foreach (array_reverse($this->rollbackPaths) as $path) {
                if (File::isDirectory($path)) {
                    File::deleteDirectory($path);
                } else {
                    File::delete($path);
                }
            }
            throw $exception;
        } finally {
            $this->rollbackPaths = [];
        }
    }
}
