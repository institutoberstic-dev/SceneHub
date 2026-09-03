<?php

namespace App\Http\Controllers;

use App\Models\Escenario;
use App\Models\EscenarioContenido;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class EscenariosController extends Controller
{
    private string $storagePath = 'app/public';

    public function index()
    {
        return view('app');
    }

    public function data(Request $request)
    {
        $user = $request->user();
        $query = Escenario::query()
            ->with([
                'owner:id,name,email',
                'users:id,name,email',
                'contenidos.uploadedBy:id,name',
                'contenidos.modifiedBy:id,name',
            ])
            ->withCount(['contenidos', 'users'])
            ->latest();

        if (! $user->hasRole('admin')) {
            $query->where(function ($accessible) use ($user) {
                $accessible->where('owner_id', $user->id)
                    ->orWhereHas('users', fn ($members) => $members->where('users.id', $user->id));
            });
        }

        return response()->json(
            $query->get()
        );
    }

    public function detail(Request $request, Escenario $escenario)
    {
        $this->authorizeReading($request, $escenario);

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
        abort_unless($request->user()->hasRole('cliente') && ! $request->user()->hasRole('admin'), 403, 'Los administradores no crean escenarios de clientes.');

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:esceanarios,nombre'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'archivo' => ['nullable', 'file', 'max:51200'],
        ]);

        $escenario = DB::transaction(function () use ($request, $validated) {
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
        abort_unless($member->hasRole('cliente'), 422, 'Solo una cuenta con rol global cliente puede participar en un escenario.');

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

        $result = DB::transaction(function () use ($request, $validated, $escenario, $user) {
            $lockedScenario = Escenario::query()->lockForUpdate()->findOrFail($escenario->id);
            $file = $request->file('archivo');
            $stored = $this->storeScenarioFile($lockedScenario, $file);

            $content = EscenarioContenido::create([
                'escenario_id' => $escenario->id,
                'uploaded_by' => $user->id,
                'modified_by' => $user->id,
                'nombre' => $validated['nombre'] ?: $file->getClientOriginalName(),
                'ruta' => $stored['path'],
                'tipo' => 'resultado',
                'mime_type' => $file->getClientMimeType(),
                'tamano' => $file->getSize() ?: 0,
                'version' => $stored['version'],
                'estado' => 'Disponible',
            ]);

            $lockedScenario->update([
                'versiones' => $stored['version'],
                'estado' => 'Activo',
            ]);

            return ['content' => $content, 'new_version' => $stored['new_version']];
        });

        return response()->json([
            'message' => $result['new_version']
                ? 'Archivo modificado y nueva versión registrada exitosamente.'
                : 'Archivo agregado a la versión actual exitosamente.',
            'data' => $result['content']->load(['uploadedBy:id,name', 'modifiedBy:id,name']),
        ], 201);
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

        $newVersion = DB::transaction(function () use ($request, $validated, $escenario, $user) {
            $lockedScenario = Escenario::query()->lockForUpdate()->findOrFail($escenario->id);
            $scenarioData = [
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'],
                'estado' => $validated['estado'],
            ];

            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $stored = $this->storeScenarioFile($lockedScenario, $file);

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

            $lockedScenario->update($scenarioData);

            return $stored['new_version'] ?? false;
        });

        return response()->json([
            'message' => $request->hasFile('archivo')
                ? ($newVersion
                    ? 'Escenario actualizado y nueva versión registrada exitosamente.'
                    : 'Escenario actualizado; el archivo fue agregado a la versión actual.')
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
        $zip = new ZipArchive();
        abort_unless($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'No fue posible preparar la descarga.');

        foreach (File::allFiles($root) as $file) {
            $zip->addFile($file->getRealPath(), str_replace('\\', '/', $file->getRelativePathname()));
        }
        $zip->close();

        return response()->download($temporary, Str::slug($escenario->nombre).'.zip')->deleteFileAfterSend(true);
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

    /** @return array{path: string, version: float, new_version: bool} */
    private function storeScenarioFile(Escenario $escenario, object $archivo, ?float $initialVersion = null): array
    {
        $scenarioRoot = $this->scenarioRoot($escenario);
        $currentVersion = $initialVersion ?? (float) $escenario->versiones;
        $currentVersion = $currentVersion < 1 ? 1.0 : $currentVersion;
        $currentDirectory = $scenarioRoot.DIRECTORY_SEPARATOR.$this->versionDirectory($currentVersion);
        $fileName = basename($archivo->getClientOriginalName());
        $currentFile = $currentDirectory.DIRECTORY_SEPARATOR.$fileName;
        $newVersion = false;

        if (File::exists($currentFile) && hash_file('sha256', $currentFile) !== hash_file('sha256', $archivo->getRealPath())) {
            $nextVersion = $this->nextVersionValue($currentVersion);
            $nextDirectory = $scenarioRoot.DIRECTORY_SEPARATOR.$this->versionDirectory($nextVersion);

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
        File::copy($archivo->getRealPath(), $currentFile);

        return [
            'path' => 'public/'.str_replace('\\', '/', $this->relativeScenarioPath($escenario, $currentVersion, $fileName)),
            'version' => $currentVersion,
            'new_version' => $newVersion,
        ];
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
}
