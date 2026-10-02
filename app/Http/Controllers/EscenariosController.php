<?php

namespace App\Http\Controllers;

use App\Models\Escenario;
use App\Models\EscenarioContenido;
use App\Models\Tecnologia;
use App\Models\User;
use App\Services\SimulationResultsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

        return response()->json(
            $query->get()
        );
    }

    public function detail(Request $request, Escenario $escenario)
    {
        return response()->json(
            $escenario->load([
                'owner:id,name,email',
                'users:id,name,email',
                'contenidos.uploadedBy:id,name',
                'contenidos.modifiedBy:id,name',
                'tecnologias.categoria',
            ])
        );
    }

    public function store(Request $request)
    {
        abort_if($request->user()->hasRole('admin'), 403, 'El administrador supervisa los escenarios, pero no crea contenido operativo.');

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:esceanarios,nombre'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'archivo' => ['required_without:archivos', ...$this->scenarioFileRules()],
            'archivos' => ['required_without:archivo', 'array', 'min:1', 'max:10'],
            'archivos.*' => $this->scenarioFileRules(),
            ...$this->technologyRules(),
        ], $this->technologyMessages());

        $technologyIds = $this->technologyIds($validated['tecnologias'] ?? []);
        $files = $this->uploadedScenarioFiles($request);
        $imports = $this->readImports($request);

        return $this->createScenario($validated, $files, $imports, $technologyIds);
    }

    private function createScenario(array $validated, array $files, array $imports, array $technologyIds = [])
    {
        $escenario = $this->withScenarioFiles(function () use ($validated, $files, $imports, $technologyIds) {
            $user = Auth::user();
            $escenario = Escenario::create([
                'nombre' => $validated['nombre'],
                'owner_id' => $user->id,
                'estado' => 'Activo',
                'descripcion' => $validated['descripcion'],
                'versiones' => $files ? 1 : 0,
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
            foreach ($files as $index => $file) {
                $stored = $this->storeScenarioFile($escenario, $file, $index === 0 ? 1.0 : null);
                $import = $imports[$index] ?? null;

                EscenarioContenido::create([
                    'escenario_id' => $escenario->id,
                    'uploaded_by' => $user->id,
                    'modified_by' => $user->id,
                    'nombre' => $file->getClientOriginalName(),
                    'ruta' => $stored['path'],
                    'tipo' => $import ? 'datos' : 'documento',
                    'mime_type' => $file->getClientMimeType(),
                    'tamano' => $file->getSize() ?: 0,
                    'version' => $stored['version'],
                    'estado' => 'Disponible',
                ]);

                if ($import) {
                    $this->persistSimulation($escenario->id, $stored['path'], $import);
                }
                if ($stored['version'] !== (float) $escenario->versiones) {
                    $escenario->update(['versiones' => $stored['version']]);
                }
            }

            return $escenario;
        });

        $imported = count(array_filter($imports));

        return response()->json([
            'message' => $this->uploadSummary(count($files), $imported, 'Escenario creado exitosamente.'),
            'data' => $escenario->load(['owner:id,name,email', 'contenidos', 'tecnologias.categoria']),
        ], 201);
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
        abort_unless($member->hasAnyRole(['cliente', 'gestor_escenarios', 'consulta']), 422, 'La cuenta no tiene un rol habilitado para participar en escenarios.');

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
        ]);

        $files = $this->uploadedScenarioFiles($request);
        $imports = $this->readImports($request);
        $result = $this->withScenarioFiles(function () use ($validated, $escenario, $user, $files, $imports) {
            $lockedScenario = Escenario::query()->lockForUpdate()->findOrFail($escenario->id);
            $contents = [];
            $storedCount = 0;
            $importedCount = 0;
            $newVersion = false;

            foreach ($files as $index => $file) {
                $import = $imports[$index] ?? null;
                $stored = $this->storeScenarioFile($lockedScenario, $file);
                $content = $stored['stored']
                    ? EscenarioContenido::create([
                        'escenario_id' => $escenario->id,
                        'uploaded_by' => $user?->id,
                        'modified_by' => $user?->id,
                        'nombre' => count($files) === 1 && ($validated['nombre'] ?? null)
                            ? $validated['nombre']
                            : $file->getClientOriginalName(),
                        'ruta' => $stored['path'],
                        'tipo' => $import ? 'datos' : 'documento',
                        'mime_type' => $file->getClientMimeType(),
                        'tamano' => $file->getSize() ?: 0,
                        'version' => $stored['version'],
                        'estado' => 'Disponible',
                    ])
                    : EscenarioContenido::query()
                        ->where('escenario_id', $escenario->id)
                        ->where('ruta', $stored['path'])
                        ->latest()
                        ->first();

                if ($import && $stored['stored']) {
                    $this->persistSimulation($escenario->id, $stored['path'], $import);
                    $importedCount++;
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
                'imported_count' => $importedCount,
            ];
        });

        $loadedContents = collect($result['contents'])
            ->map->load(['uploadedBy:id,name', 'modifiedBy:id,name'])
            ->values();

        return response()->json([
            'message' => $result['stored_count'] === 0
                ? 'Los archivos no presentan cambios; no se almacenaron copias duplicadas.'
                : $this->uploadSummary($result['stored_count'], $result['imported_count'], 'Archivos almacenados exitosamente.'),
            'stored' => $result['stored_count'] > 0,
            'new_version' => $result['new_version'],
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

        $import = $this->readImport($request->file('archivo'), 'archivo');

        $newVersion = $this->withScenarioFiles(function () use ($request, $validated, $escenario, $user, $import, $syncTechnologies, $technologyIds) {
            $lockedScenario = Escenario::query()->lockForUpdate()->findOrFail($escenario->id);
            $scenarioData = [
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'],
                'estado' => $validated['estado'],
            ];

            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $stored = $this->storeScenarioFile($lockedScenario, $file);

                if ($stored['stored']) {
                    EscenarioContenido::create([
                        'escenario_id' => $escenario->id,
                        'uploaded_by' => $user->id,
                        'modified_by' => $user->id,
                        'nombre' => $file->getClientOriginalName(),
                        'ruta' => $stored['path'],
                        'tipo' => $import ? 'datos' : 'documento',
                        'mime_type' => $file->getClientMimeType(),
                        'tamano' => $file->getSize() ?: 0,
                        'version' => $stored['version'],
                        'estado' => 'Disponible',
                    ]);

                    $scenarioData['versiones'] = $stored['version'];
                }
            }

            if (isset($stored) && $stored['stored'] && $import) {
                $this->persistSimulation($escenario->id, $stored['path'], $import);
            }
            $lockedScenario->update($scenarioData);
            if ($syncTechnologies) {
                $lockedScenario->tecnologias()->sync($technologyIds);
            }

            return $stored ?? null;
        });

        return response()->json([
            'message' => $request->hasFile('archivo')
                ? (! $newVersion['stored']
                    ? 'Escenario actualizado; el archivo no cambió y no se almacenó nuevamente.'
                    : ($newVersion['new_version']
                        ? 'Escenario actualizado y nueva versión registrada exitosamente.'
                        : 'Escenario actualizado; el archivo fue agregado a la versión actual.'))
                : 'Escenario actualizado exitosamente.',
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
     */
    private function readImports(Request $request): array
    {
        $imports = [];
        $errors = [];
        foreach ($this->uploadedScenarioFilesByField($request) as $field => $file) {
            try {
                $imports[] = $this->readImport($file, $field);
            } catch (ValidationException $exception) {
                $errors = array_merge_recursive($errors, $exception->errors());
                $imports[] = null;
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
        return ['file', 'max:51200', 'mimes:xlsx,xls,doc,docx', 'extensions:xlsx,xls,doc,docx'];
    }

    /** @return array<int, \Illuminate\Http\UploadedFile> */
    private function uploadedScenarioFiles(Request $request): array
    {
        return array_values($this->uploadedScenarioFilesByField($request));
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

    private function uploadSummary(int $stored, int $imported, string $prefix): string
    {
        $documents = $stored - $imported;
        $parts = [];
        if ($imported > 0) {
            $parts[] = $imported.' '.($imported === 1 ? 'documento fue importado a resultados' : 'documentos fueron importados a resultados');
        }
        if ($documents > 0) {
            $parts[] = $documents.' '.($documents === 1 ? 'archivo quedó disponible para descarga' : 'archivos quedaron disponibles para descarga');
        }

        return $parts ? $prefix.' '.ucfirst(implode(' y ', $parts)).'.' : $prefix;
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

    /** @return array{path: string, version: float, new_version: bool, stored: bool} */
    private function storeScenarioFile(Escenario $escenario, object $archivo, ?float $initialVersion = null): array
    {
        // Compare with the latest file only: restoring an older result is a new variation.
        $scenarioRoot = $this->scenarioRoot($escenario);
        $currentVersion = $initialVersion ?? (float) $escenario->versiones;
        $currentVersion = $currentVersion < 1 ? 1.0 : $currentVersion;
        $currentDirectory = $scenarioRoot.DIRECTORY_SEPARATOR.$this->versionDirectory($currentVersion);
        $fileName = basename($archivo->getClientOriginalName());
        $currentFile = $currentDirectory.DIRECTORY_SEPARATOR.$fileName;
        $newVersion = false;
        $latestExistingFile = $this->latestVersionedFile($scenarioRoot, $fileName);

        if ($latestExistingFile !== null
            && hash_file('sha256', $latestExistingFile['path']) === hash_file('sha256', $archivo->getRealPath())) {
            return [
                'path' => 'public/'.str_replace('\\', '/', $this->relativeScenarioPath(
                    $escenario,
                    $latestExistingFile['version'],
                    $fileName
                )),
                'version' => $currentVersion,
                'new_version' => false,
                'stored' => false,
            ];
        }

        if ($latestExistingFile !== null
            && hash_file('sha256', $latestExistingFile['path']) !== hash_file('sha256', $archivo->getRealPath())) {
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

    /** @return array{path: string, version: float}|null */
    private function latestVersionedFile(string $scenarioRoot, string $fileName): ?array
    {
        if (! File::isDirectory($scenarioRoot)) {
            return null;
        }

        $versionDirectories = collect(File::directories($scenarioRoot))
            ->filter(fn (string $directory) => is_numeric(basename($directory)))
            ->sortByDesc(fn (string $directory) => (float) basename($directory));

        foreach ($versionDirectories as $directory) {
            $candidate = $directory.DIRECTORY_SEPARATOR.$fileName;

            if (File::isFile($candidate)) {
                return [
                    'path' => $candidate,
                    'version' => (float) basename($directory),
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
