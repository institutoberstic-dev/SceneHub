<?php

namespace App\Http\Controllers;

use App\Models\Escenario;
use App\Models\EscenarioContenido;
use App\Models\User;
use App\Services\ScenarioSolarImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
            ])
        );
    }

    public function store(Request $request)
    {
        abort_if($request->user()->hasRole('admin'), 403, 'El administrador supervisa los escenarios, pero no crea contenido operativo.');

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:esceanarios,nombre'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'archivo' => ['required', 'file', 'max:51200'],
        ]);

        $import = $this->readImport($request->file('archivo'));

        return $this->createScenario($request, $validated, $import);
    }

    private function createScenario(Request $request, array $validated, ?array $import)
    {
        $escenario = $this->withScenarioFiles(function () use ($request, $validated, $import) {
            $user = Auth::user();
            $escenario = Escenario::create([
                'nombre' => $validated['nombre'],
                'owner_id' => $user->id,
                'estado' => 'Activo',
                'descripcion' => $validated['descripcion'],
                'versiones' => $request->hasFile('archivo') ? 1 : 0,
            ]);
            $escenario->update([
                'storage_directory' => $this->scenarioDirectory($escenario),
            ]);

            $escenario->users()->attach($user->id, [
                'access_level' => 'owner',
                'invited_by' => $user->id,
            ]);

            $this->rollbackPaths[] = $this->scenarioRoot($escenario);
            File::ensureDirectoryExists($this->scenarioRoot($escenario));
            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $stored = $this->storeScenarioFile($escenario, $file, 1.0);

                EscenarioContenido::create([
                    'escenario_id' => $escenario->id,
                    'uploaded_by' => $user->id,
                    'modified_by' => $user->id,
                    'nombre' => $file->getClientOriginalName(),
                    'ruta' => $stored['path'],
                    'tipo' => 'archivo',
                    'mime_type' => $file->getClientMimeType(),
                    'tamano' => $file->getSize() ?: 0,
                    'version' => $stored['version'],
                    'estado' => 'Disponible',
                ]);
            }

            if (isset($stored)) $this->persistSolar($escenario->id, $stored['path'], $import);

            return $escenario;
        });

        return response()->json([
            'message' => 'Escenario creado exitosamente.',
            'data' => $escenario->load(['owner:id,name,email', 'contenidos']),
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
            'archivo' => ['required', 'file', 'max:51200'],
        ]);

        $import = $this->readImport($request->file('archivo'));
        $result = $this->withScenarioFiles(function () use ($request, $validated, $escenario, $user, $import) {
            $lockedScenario = Escenario::query()->lockForUpdate()->findOrFail($escenario->id);
            $file = $request->file('archivo');
            $stored = $this->storeScenarioFile($lockedScenario, $file);

            $content = $stored['stored']
                ? EscenarioContenido::create([
                    'escenario_id' => $escenario->id,
                    'uploaded_by' => $user?->id,
                    'modified_by' => $user?->id,
                    'nombre' => ($validated['nombre'] ?? null) ?: $file->getClientOriginalName(),
                    'ruta' => $stored['path'],
                    'tipo' => 'resultado',
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

            $this->persistSolar($escenario->id, $stored['path'], $import);

            if ($stored['stored']) {
                $lockedScenario->update([
                    'versiones' => $stored['version'],
                    'estado' => 'Activo',
                ]);
            }

            return [
                'content' => $content,
                'new_version' => $stored['new_version'],
                'stored' => $stored['stored'],
            ];
        });

        return response()->json([
            'message' => ! $result['stored']
                ? 'El archivo no presenta cambios; no se almacenó una copia duplicada.'
                : ($result['new_version']
                    ? 'Archivo modificado y nueva versión registrada exitosamente.'
                    : 'Archivo agregado a la versión actual exitosamente.'),
            'stored' => $result['stored'],
            'new_version' => $result['new_version'],
            'data' => $result['content']?->load(['uploadedBy:id,name', 'modifiedBy:id,name']),
        ], $result['stored'] ? 201 : 200);
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
                'data' => $escenario->fresh()->load(['owner:id,name,email']),
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
            'archivo' => ['nullable', 'file', 'max:51200'],
        ]);

        abort_if(
            $request->hasFile('archivo')
                && $request->attributes->get('scenario_access_level') !== 'owner',
            403,
            'Tu rol permite editar el escenario, pero no reemplazar archivos ni crear versiones.'
        );

        $import = $this->readImport($request->file('archivo'));


        $newVersion = $this->withScenarioFiles(function () use ($request, $validated, $escenario, $user, $import) {
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
                        'tipo' => 'actualizacion',
                        'mime_type' => $file->getClientMimeType(),
                        'tamano' => $file->getSize() ?: 0,
                        'version' => $stored['version'],
                        'estado' => 'Disponible',
                    ]);

                    $scenarioData['versiones'] = $stored['version'];
                }
            }

            if (isset($stored)) $this->persistSolar($escenario->id, $stored['path'], $import);
            $lockedScenario->update($scenarioData);

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
            'data' => $escenario->fresh()->load(['owner:id,name,email', 'contenidos.uploadedBy:id,name', 'contenidos.modifiedBy:id,name']),
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

    private function readImport(?\Illuminate\Http\UploadedFile $file): ?array
    {
        return app(ScenarioSolarImport::class)->read($file);
    }

    private function persistSolar(int $scenarioId, string $path, ?array $import): void
    {
        $content = EscenarioContenido::where('escenario_id', $scenarioId)->where('ruta', $path)->latest('id')->firstOrFail();
        app(ScenarioSolarImport::class)->persist($scenarioId, $content->id, $import);
        app(\App\Services\DataVersions::class)->register($scenarioId, $content->id, 'solar', $import['rows']);
    }

    public function solarResults(Request $request, Escenario $escenario)
    {
        $mapping = [
            'caudal' => 'caudal_m3_h', 'radiacion_solar' => 'irradiancia_w_m2',
            'temperatura' => 'temperatura_c', 'velocidad_viento' => 'velocidad_viento_m_s',
            'potencia_solar' => 'potencia_solar_w', 'potencia_neta' => 'potencia_neta_w',
            'consumo_planta' => 'potencia_consumida_planta_w',
            'agua_desalinizada' => 'agua_desalinizada_acum_m3', 'salmuera' => 'salmuera_acum_m3',
            'lodos_gruesos' => 'lodos_gruesos_acum_paquetes', 'lodos_finos' => 'lodos_finos_acum_paquetes',
        ];
        $groups = DB::table('solar_data')->where('escenario_id', $escenario->id)
            ->orderBy('tiempo_minutos')->get()->groupBy('archivo_id');
        $files = $escenario->contenidos()->whereIn('id', $groups->keys())->get();
        return response()->json(['archivos' => $files->map(function ($file) use ($groups, $mapping) {
            $samples = ['1' => [], '5' => [], '10' => [], '60' => []];
            foreach ($groups[$file->id] as $row) {
                $point = ['tiempo_minutos' => (int) $row->tiempo_minutos,
                    'energia_almacenada_wh' => null,
                    'energia_almacenada_original' => $row->energia_almacenada];
                foreach ($mapping as $source => $target) $point[$target] = $row->$source === null ? null : (float) $row->$source;
                $samples[(string) $row->intervalo_minutos][] = $point;
            }
            $version = DB::table('data_versions')->where('archivo_id', $file->id)->where('tipo', 'solar')->orderByDesc('revision')->value('version');
            return ['id' => $file->id, 'nombre' => $file->nombre, 'version_datos' => $version, 'muestreos' => $samples,
                'advertencias' => ['La unidad de energía almacenada está pendiente de confirmar; se conserva el valor original rotulado W.']];
        })->values()]);
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
