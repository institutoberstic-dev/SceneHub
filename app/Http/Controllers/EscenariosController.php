<?php

namespace App\Http\Controllers;

use App\Models\Escenario;
use App\Models\EscenarioContenido;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use STS\ZipStream\Facades\Zip;

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

    public function store(Request $request)
    {
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

            $escenario->users()->attach($user->id, [
                'access_level' => 'owner',
                'invited_by' => $user->id,
            ]);

            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $ruta = $this->empaquetarArchivos($file, "escenario-{$escenario->id}-v1");

                EscenarioContenido::create([
                    'escenario_id' => $escenario->id,
                    'uploaded_by' => $user->id,
                    'modified_by' => $user->id,
                    'nombre' => $file->getClientOriginalName(),
                    'ruta' => $ruta,
                    'tipo' => 'paquete',
                    'mime_type' => $file->getClientMimeType(),
                    'tamano' => $file->getSize() ?: 0,
                    'version' => 1,
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

        abort_unless($actor->hasRole('admin') || $escenario->owner_id === $actor->id, 403, 'Solo el owner del escenario puede gestionar sus accesos.');

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

    public function uploadContent(Request $request, Escenario $escenario)
    {
        $user = $request->user();
        $hasAccess = $user->hasRole('admin')
            || $escenario->owner_id === $user->id
            || $escenario->users()->where('users.id', $user->id)->exists();

        abort_unless($hasAccess, 403, 'No tienes acceso a este escenario.');

        $validated = $request->validate([
            'nombre' => ['nullable', 'string', 'max:255'],
            'archivo' => ['required', 'file', 'max:51200'],
        ]);

        $content = DB::transaction(function () use ($request, $validated, $escenario, $user) {
            $nextVersion = $this->nextVersion($escenario);
            $file = $request->file('archivo');
            $ruta = $this->empaquetarArchivos($file, "escenario-{$escenario->id}-v{$nextVersion}");

            $content = EscenarioContenido::create([
                'escenario_id' => $escenario->id,
                'uploaded_by' => $user->id,
                'modified_by' => $user->id,
                'nombre' => $validated['nombre'] ?: $file->getClientOriginalName(),
                'ruta' => $ruta,
                'tipo' => 'resultado',
                'mime_type' => $file->getClientMimeType(),
                'tamano' => $file->getSize() ?: 0,
                'version' => $nextVersion,
                'estado' => 'Disponible',
            ]);

            $escenario->update([
                'versiones' => $nextVersion,
                'estado' => 'Activo',
            ]);

            return $content;
        });

        return response()->json([
            'message' => 'Resultado cargado y versionado exitosamente.',
            'data' => $content->load(['uploadedBy:id,name', 'modifiedBy:id,name']),
        ], 201);
    }

    public function update(Request $request, Escenario $escenario)
    {
        $user = $request->user();
        $hasAccess = $user->hasRole('admin')
            || $escenario->owner_id === $user->id
            || $escenario->users()->where('users.id', $user->id)->exists();

        abort_unless($hasAccess, 403, 'No tienes acceso a este escenario.');

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:esceanarios,nombre,'.$escenario->id],
            'descripcion' => ['required', 'string', 'max:2000'],
            'estado' => ['required', 'in:Activo,Inactivo'],
            'archivo' => ['nullable', 'file', 'max:51200'],
        ]);

        abort_if(
            $request->hasFile('archivo')
                && ! $user->hasRole('admin')
                && $request->attributes->get('scenario_access_level') !== 'owner',
            403,
            'Tu rol permite editar el escenario, pero no reemplazar archivos ni crear versiones.'
        );

        DB::transaction(function () use ($request, $validated, $escenario, $user) {
            $scenarioData = [
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'],
                'estado' => $validated['estado'],
            ];

            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $nextVersion = $this->nextVersion($escenario);
                $ruta = $this->empaquetarArchivos($file, "escenario-{$escenario->id}-v{$nextVersion}");

                EscenarioContenido::create([
                    'escenario_id' => $escenario->id,
                    'uploaded_by' => $user->id,
                    'modified_by' => $user->id,
                    'nombre' => $file->getClientOriginalName(),
                    'ruta' => $ruta,
                    'tipo' => 'actualizacion',
                    'mime_type' => $file->getClientMimeType(),
                    'tamano' => $file->getSize() ?: 0,
                    'version' => $nextVersion,
                    'estado' => 'Disponible',
                ]);

                $scenarioData['versiones'] = $nextVersion;
            }

            $escenario->update($scenarioData);
        });

        return response()->json([
            'message' => $request->hasFile('archivo')
                ? 'Escenario actualizado y nueva versión registrada exitosamente.'
                : 'Escenario actualizado exitosamente.',
            'data' => $escenario->fresh()->load(['owner:id,name,email', 'contenidos.uploadedBy:id,name', 'contenidos.modifiedBy:id,name']),
        ]);
    }

    public function destroy(Escenario $escenario)
    {
        return response()->json(['message' => 'Operación aún no implementada.'], 501);
    }

    private function empaquetarArchivos(object $archivo, ?string $archiveStem = null): string
    {
        $nombreOriginal = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
        $nombreZip = ($archiveStem ?: $nombreOriginal).'.zip';
        $directorio = storage_path($this->storagePath);

        File::ensureDirectoryExists($directorio);

        Zip::create($nombreZip, [
            $archivo->getRealPath() => $archivo->getClientOriginalName(),
        ])->saveTo($directorio.DIRECTORY_SEPARATOR.$nombreZip);

        return 'public/'.$nombreZip;
    }

    private function nextVersion(Escenario $escenario): float
    {
        $currentVersion = (float) $escenario->versiones;

        return $currentVersion < 1 ? 1.0 : round($currentVersion + 0.1, 1);
    }
}
