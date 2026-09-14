<?php

namespace App\Http\Controllers;

use App\Models\Escenario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DataVersionsController extends Controller
{
    private function versions(Request $request, Escenario $escenario, string $type)
    {
        $user = $request->user();
        abort_unless($user->hasRole('admin') || $escenario->owner_id === $user->id || $escenario->users()->where('users.id', $user->id)->exists(), 403);
        return DB::table('data_versions')->where('escenario_id', $escenario->id)->where('tipo', $type);
    }

    public function manifest(Request $request, Escenario $escenario)
    {
        $request->validate(['tipo' => ['required', Rule::in(['emociones_promedio', 'solar'])], 'version_actual' => ['nullable', 'regex:/^\d+\.\d+$/'], 'sha256_actual' => ['nullable', 'regex:/^[a-f0-9]{64}$/']]);
        $query = $this->versions($request, $escenario, $request->tipo);
        $latest = (clone $query)->orderByDesc('revision')->first();
        $state = ! $latest ? 'sin_datos' : (! $request->filled('version_actual') ? 'descarga_inicial' : ($request->version_actual === $latest->version ? 'sin_cambios' : 'actualizacion_disponible'));
        if ($latest && $request->filled('version_actual') && version_compare($request->version_actual, $latest->version, '>')) $state = 'version_local_posterior';
        if ($latest && $request->version_actual === $latest->version && $request->filled('sha256_actual') && $request->sha256_actual !== $latest->sha256) $state = 'actualizacion_disponible';
        $messages = ['sin_datos' => 'No hay datos disponibles para este escenario.', 'descarga_inicial' => 'Hay datos disponibles para descargar.', 'sin_cambios' => 'Ya tienes la última versión. Puedes continuar con tus datos guardados.', 'actualizacion_disponible' => 'Hay una actualización disponible. Pulsa Actualizar para descargarla.', 'version_local_posterior' => 'La versión local es posterior a la del servidor. Conserva los datos locales y revisa el escenario.'];
        return response()->json(['escenario_id' => $escenario->id, 'tipo' => $request->tipo, 'estado' => $state, 'mensaje' => $messages[$state], 'hay_datos' => (bool) $latest, 'actualizacion_disponible' => $state === 'actualizacion_disponible', 'descarga_inicial' => $state === 'descarga_inicial', 'version' => $latest->version ?? null, 'sha256' => $latest->sha256 ?? null, 'versiones' => $query->orderBy('revision')->get(['version', 'revision', 'created_at']), 'url_datos' => $latest ? route($request->tipo === 'solar' ? 'datos.solar' : 'datos.emociones', ['escenario' => $escenario->id, 'version' => $latest->version], false) : null])->header('Cache-Control', 'private, no-store');
    }

    public function overview(Request $request, Escenario $escenario)
    {
        $this->versions($request, $escenario, 'emociones_promedio');
        $all = DB::table('data_versions')
            ->where('escenario_id', $escenario->id)
            ->orderByDesc('revision')
            ->get();
        $latest = $all->groupBy('tipo')->map->first();
        $resource = function (string $type, string $route) use ($latest, $all, $escenario) {
            $current = $latest->get($type);
            return [
                'disponible' => (bool) $current,
                'version' => $current->version ?? null,
                'revision' => $current->revision ?? null,
                'sha256' => $current->sha256 ?? null,
                'url' => $current ? route($route, ['escenario' => $escenario->id, 'version' => $current->version], false) : null,
                'url_versiones' => route('datos.versiones', ['escenario' => $escenario->id, 'tipo' => $type], false),
                'versiones' => $all->where('tipo', $type)->sortBy('revision')->values()->map(fn ($version) => [
                    'version' => $version->version,
                    'revision' => $version->revision,
                    'sha256' => $version->sha256,
                    'created_at' => $version->created_at,
                ]),
            ];
        };

        return response()->json([
            'escenario' => [
                'id' => $escenario->id,
                'nombre' => $escenario->nombre,
                'descripcion' => $escenario->descripcion,
                'estado' => $escenario->estado,
                'versiones' => $escenario->versiones,
                'created_at' => $escenario->created_at,
                'updated_at' => $escenario->updated_at,
            ],
            'datos' => [
                'emociones_promedio' => $resource('emociones_promedio', 'datos.emociones'),
                'solar' => $resource('solar', 'datos.solar'),
            ],
            'url_actualizacion' => route('datos.actualizar', ['escenario' => $escenario->id], false),
            'metodo_actualizacion' => 'POST multipart/form-data con archivo y nombre opcional',
        ])->header('Cache-Control', 'private, no-store');
    }

    private function selected(Request $request, Escenario $escenario, string $type): ?object
    {
        $request->validate(['version' => ['nullable', 'regex:/^\d+\.\d+$/']]);
        $query = $this->versions($request, $escenario, $type);
        if ($request->filled('version')) $query->where('version', $request->version);
        $selected = $query->orderByDesc('revision')->first();
        abort_if($request->filled('version') && ! $selected, 404, 'Versión no disponible.');
        return $selected;
    }

    public function averages(Request $request, Escenario $escenario)
    {
        $selected = $this->selected($request, $escenario, 'emociones_promedio');
        $rows = $selected ? DB::table('emotions_prom')->where('data_version_id', $selected->id)->orderBy('id_meeting')->get(['id_meeting', 'nivel_atencion_prom', 'emocion_ganadora_prom']) : [];
        return response()->json(['escenario_id' => $escenario->id, 'tipo' => 'emociones_promedio', 'version' => $selected->version ?? null, 'sha256' => $selected->sha256 ?? null, 'hay_datos' => (bool) $selected, 'datos' => $rows])->header('Cache-Control', 'private, no-store');
    }

    public function solar(Request $request, Escenario $escenario)
    {
        $selected = $this->selected($request, $escenario, 'solar');
        $samples = ['1' => [], '5' => [], '10' => []];
        if ($selected) {
            foreach (DB::table('solar_data')->where('archivo_id', $selected->archivo_id)->orderBy('tiempo_minutos')->get() as $row) {
                $point = (array) $row;
                foreach (['id', 'escenario_id', 'archivo_id', 'created_at', 'updated_at'] as $key) unset($point[$key]);
                $samples[(string) $row->intervalo_minutos][] = $point;
            }
        }
        return response()->json(['escenario_id' => $escenario->id, 'tipo' => 'solar', 'version' => $selected->version ?? null, 'sha256' => $selected->sha256 ?? null, 'hay_datos' => (bool) $selected, 'muestreos' => $samples, 'unidades' => ['caudal' => 'm3/h', 'radiacion_solar' => 'W/m2', 'temperatura' => '°C', 'velocidad_viento' => 'm/s', 'potencia_solar' => 'W', 'potencia_neta' => 'W', 'consumo_planta' => 'W', 'energia_almacenada' => null, 'agua_desalinizada' => 'm3', 'salmuera' => 'm3', 'lodos_gruesos' => 'paquetes de 10 kg', 'lodos_finos' => 'paquetes de 10 kg'], 'advertencias' => ['Energía almacenada conserva el valor original; el Excel la rotula W, unidad pendiente de validar.']])->header('Cache-Control', 'private, no-store');
    }
}
