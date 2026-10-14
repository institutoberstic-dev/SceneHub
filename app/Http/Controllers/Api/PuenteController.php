<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Puente\EscenarioPuenteResource;
use App\Http\Resources\Puente\PaqueteTerritorialPuenteResource;
use App\Models\ArchivoTerritorial;
use App\Models\Escenario;
use App\Models\EscenarioContenido;
use App\Models\PaqueteTerritorial;
use App\Services\SimulationResultsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API de SOLO LECTURA para la app de escritorio «Puente de datos» (prefijo /api/puente/v1).
 * Protegida por token (middleware puente.token). Contrato: docs/puente-anh/CONTRATO_API.md.
 */
class PuenteController extends Controller
{
    public const VERSION_API = '1';

    public function estado(): JsonResponse
    {
        return response()->json(['servicio' => 'SceneHub', 'version_api' => self::VERSION_API, 'hora_servidor' => now()->toIso8601String()]);
    }

    public function catalogoVariables(): JsonResponse
    {
        return response()->json(['variables' => config('catalogo_variables.variables')]);
    }

    public function escenarios(): JsonResponse
    {
        $escenarios = Escenario::query()->with('tecnologias.categoria')->orderBy('id')->get();
        $versiones = DB::table('data_versions')
            ->whereIn('escenario_id', $escenarios->pluck('id'))
            ->where('tipo', SimulationResultsImport::VERSION_TYPE)
            ->orderBy('revision')
            ->get()
            ->groupBy('escenario_id');
        $contenidos = EscenarioContenido::query()->whereIn('id', $versiones->flatten()->pluck('archivo_id'))->get()->keyBy('id');

        foreach ($escenarios as $escenario) {
            $escenario->setAttribute('versiones_puente', $versiones->get($escenario->id, collect())->map(function ($version) use ($escenario, $contenidos) {
                $contenido = $contenidos->get($version->archivo_id);
                $ruta = $contenido ? $this->rutaContenido($contenido) : null;
                $existe = $ruta && File::isFile($ruta);

                return [
                    'version' => $version->version,
                    'revision' => (int) $version->revision,
                    'sha256' => $version->sha256,
                    'publicada_en' => $version->created_at ? Carbon::parse($version->created_at)->toIso8601String() : null,
                    'archivo' => $existe ? [
                        'id' => $contenido->id,
                        'nombre' => basename(str_replace('\\', '/', $contenido->ruta)),
                        'tamano' => filesize($ruta),
                        'sha256_archivo' => hash_file('sha256', $ruta),
                        'url_descarga' => "escenarios/{$escenario->id}/versiones/{$version->version}/archivo",
                    ] : null,
                ];
            })->values()->all());
        }

        return response()->json(['escenarios' => EscenarioPuenteResource::collection($escenarios)->resolve()]);
    }

    public function archivoVersion(Escenario $escenario, string $version): BinaryFileResponse
    {
        abort_unless(preg_match('/^\d+\.\d+$/', $version) === 1, 404);
        $registro = DB::table('data_versions')
            ->where('escenario_id', $escenario->id)
            ->where('tipo', SimulationResultsImport::VERSION_TYPE)
            ->where('version', $version)
            ->first();
        abort_unless($registro, 404, 'Versión no disponible.');
        $contenido = EscenarioContenido::query()->where('escenario_id', $escenario->id)->find($registro->archivo_id);
        $ruta = $contenido ? $this->rutaContenido($contenido) : null;
        abort_unless($ruta && File::isFile($ruta), 404, 'El archivo de esa versión ya no está disponible.');

        return response()->download($ruta, basename(str_replace('\\', '/', $contenido->ruta)), [
            'X-Content-SHA256' => hash_file('sha256', $ruta),
        ]);
    }

    public function paquetesTerritoriales(): JsonResponse
    {
        $paquetes = PaqueteTerritorial::query()
            ->whereIn('estado', [PaqueteTerritorial::PUBLICADO, PaqueteTerritorial::RETIRADO])
            ->with('archivos')
            ->orderBy('publicado_en')
            ->get();

        return response()->json(['paquetes' => PaqueteTerritorialPuenteResource::collection($paquetes)->resolve()]);
    }

    public function archivoTerritorial(ArchivoTerritorial $archivo): StreamedResponse
    {
        // Solo se entregan archivos de versiones publicadas (o retiradas, como historial).
        abort_unless(in_array($archivo->paquete->estado, [PaqueteTerritorial::PUBLICADO, PaqueteTerritorial::RETIRADO], true), 404);
        $disco = Storage::disk(config('territorio.disco'));
        abort_unless($disco->exists($archivo->ruta), 404, 'El archivo ya no está disponible.');

        return $disco->download($archivo->ruta, $archivo->nombre_original, ['X-Content-SHA256' => $archivo->sha256]);
    }

    private function rutaContenido(EscenarioContenido $contenido): string
    {
        return storage_path('app'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $contenido->ruta));
    }
}
