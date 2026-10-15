<?php

namespace App\Http\Controllers;

use App\Http\Requests\PuenteApp\PublicarVersionRequest;
use App\Models\PuenteVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de la app de escritorio «Puente de datos» (pantallas web con sesión).
 * Permisos: puente.descargar (ver y descargar) y puente.publicar (subir y retirar versiones).
 */
class PuenteAppController extends Controller
{
    public function index()
    {
        return view('app');
    }

    public function data(Request $request): JsonResponse
    {
        $puedePublicar = $request->user()->can('puente.publicar');
        $versiones = PuenteVersion::query()
            ->with(['subidoPor:id,name', 'retiradaPor:id,name'])
            ->when(! $puedePublicar, fn ($q) => $q->vigentes())
            ->orderByDesc('id')
            ->get();

        $vigentes = collect(config('puente_app.tipos'))
            ->map(fn (array $tipo, string $clave) => $versiones->first(fn (PuenteVersion $v) => $v->tipo === $clave && $v->vigente()))
            ->filter()
            ->map(fn (PuenteVersion $v) => $this->fila($v, $puedePublicar))
            ->values();

        return response()->json([
            'vigentes' => $vigentes,
            'versiones' => $puedePublicar ? $versiones->map(fn (PuenteVersion $v) => $this->fila($v, true))->values() : [],
            'tipos' => collect(config('puente_app.tipos'))->map(fn (array $t, string $clave) => ['clave' => $clave] + $t)->values(),
            'max_mb' => config('puente_app.max_mb'),
            'requisitos' => config('puente_app.requisitos'),
            'url_scenehub' => $request->getSchemeAndHttpHost(),
            'puede_publicar' => $puedePublicar,
        ]);
    }

    public function store(PublicarVersionRequest $request): JsonResponse
    {
        $archivo = $request->file('archivo');
        $datos = $request->validated();
        $disco = Storage::disk(config('puente_app.disco'));
        $nombre = Str::uuid()->toString().'.'.strtolower($archivo->getClientOriginalExtension());
        $ruta = $disco->putFileAs(config('puente_app.carpeta').'/'.$datos['version'], $archivo, $nombre);

        try {
            $version = PuenteVersion::create([
                'version' => $datos['version'],
                'tipo' => $datos['tipo'],
                'notas' => $datos['notas'] ?? null,
                'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                'ruta' => $ruta,
                'tamano' => $archivo->getSize(),
                'sha256' => hash_file('sha256', $disco->path($ruta)),
                'subido_por' => $request->user()->id,
            ]);
        } catch (\Throwable $error) {
            $disco->delete($ruta); // sin registro no queda archivo huérfano
            throw $error;
        }

        return response()->json([
            'message' => "Versión {$version->version} ({$version->tipo}) publicada. Ya se puede descargar.",
            'data' => $this->fila($version->load('subidoPor:id,name'), true),
        ], 201);
    }

    public function descargar(Request $request, PuenteVersion $puenteVersion): StreamedResponse
    {
        abort_unless($puenteVersion->vigente() || $request->user()->can('puente.publicar'), 404);
        $disco = Storage::disk(config('puente_app.disco'));
        abort_unless($disco->exists($puenteVersion->ruta), 404, 'El archivo ya no está disponible.');
        $puenteVersion->increment('descargas');

        return $disco->download($puenteVersion->ruta, $puenteVersion->nombreDescarga(), [
            'X-Content-SHA256' => $puenteVersion->sha256,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function retirar(Request $request, PuenteVersion $puenteVersion): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:500']], [
            'motivo.min' => 'Explique en al menos 10 caracteres por qué se retira esta versión.',
        ]);
        abort_unless($puenteVersion->vigente(), 422, 'Esa versión ya estaba retirada.');
        $puenteVersion->update(['retirada_en' => now(), 'retirada_por' => $request->user()->id, 'motivo_retiro' => $datos['motivo']]);

        return response()->json(['message' => "Versión {$puenteVersion->version} retirada. Ya no aparece para descargar."]);
    }

    private function fila(PuenteVersion $v, bool $detalle): array
    {
        return [
            'id' => $v->id,
            'version' => $v->version,
            'tipo' => $v->tipo,
            'tipo_nombre' => config("puente_app.tipos.{$v->tipo}.nombre", $v->tipo),
            'notas' => $v->notas,
            'nombre_descarga' => $v->nombreDescarga(),
            'tamano' => $v->tamano,
            'sha256' => $v->sha256,
            'publicada_en' => $v->created_at?->toIso8601String(),
            'url_descarga' => route('puente_app.descargar', $v, false),
            'vigente' => $v->vigente(),
        ] + ($detalle ? [
            'descargas' => $v->descargas,
            'subido_por' => $v->subidoPor?->name,
            'retirada_en' => $v->retirada_en?->toIso8601String(),
            'retirada_por' => $v->retiradaPor?->name,
            'motivo_retiro' => $v->motivo_retiro,
        ] : []);
    }
}
