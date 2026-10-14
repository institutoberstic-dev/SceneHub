<?php

namespace App\Http\Controllers;

use App\Http\Requests\Territorio\CrearPaqueteRequest;
use App\Http\Requests\Territorio\RetirarPaqueteRequest;
use App\Http\Requests\Territorio\SubirArchivoRequest;
use App\Models\ArchivoTerritorial;
use App\Models\PaqueteTerritorial;
use App\Models\ValidacionTerritorial;
use App\Services\Territorio\PaquetesTerritoriales;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Módulo territorial (pantallas web con sesión). Repositorio versionado: sin mapas ni geoprocesos. */
class TerritorioController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private PaquetesTerritoriales $paquetes) {}

    public function index()
    {
        return view('app');
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaqueteTerritorial::class);
        $paquetes = PaqueteTerritorial::query()
            ->with(['creador:id,name', 'validaciones'])
            ->withCount('archivos')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PaqueteTerritorial $p) => [
                'id' => $p->id,
                'version' => $p->version,
                'estado' => $p->estado,
                'notas' => $p->notas,
                'archivos_count' => $p->archivos_count,
                'errores' => $p->validaciones->where('resultado', ValidacionTerritorial::ERROR)->count(),
                'advertencias_pendientes' => $p->validaciones->filter(fn ($v) => $v->resultado === ValidacionTerritorial::ADVERTENCIA && $v->requiere_confirmacion && ! $v->confirmada_en)->count(),
                'creado_por' => $p->creador?->name,
                'creado_en' => $p->created_at?->toIso8601String(),
                'publicado_en' => $p->publicado_en?->toIso8601String(),
            ]);

        return response()->json([
            'paquetes' => $paquetes,
            'siguiente_version' => $this->paquetes->siguienteVersion(),
            'puede_crear' => $request->user()->can('create', PaqueteTerritorial::class),
        ]);
    }

    public function store(CrearPaqueteRequest $request): JsonResponse
    {
        $paquete = $this->paquetes->crear($request->validated('version'), $request->validated('notas'), $request->user());

        return response()->json(['message' => "Borrador {$paquete->version} creado.", 'data' => ['id' => $paquete->id]], 201);
    }

    public function detail(Request $request, PaqueteTerritorial $paquete): JsonResponse
    {
        $this->authorize('view', $paquete);

        return response()->json($this->detalle($request, $paquete));
    }

    public function subir(SubirArchivoRequest $request, PaqueteTerritorial $paquete): JsonResponse
    {
        $archivo = $this->paquetes->subirArchivo($paquete, $request->file('archivo'), $request->safe()->except('archivo'), $request->user());
        $errores = ValidacionTerritorial::query()->where('archivo_id', $archivo->id)->where('resultado', ValidacionTerritorial::ERROR)->count();

        return response()->json([
            'message' => $errores ? "«{$archivo->nombre_original}» se guardó con {$errores} error(es) de validación. Revise el detalle." : "«{$archivo->nombre_original}» se guardó y validó.",
            'data' => $this->detalle($request, $paquete->fresh()),
        ], 201);
    }

    public function eliminarArchivo(Request $request, PaqueteTerritorial $paquete, ArchivoTerritorial $archivo): JsonResponse
    {
        $this->authorize('subir', $paquete);
        abort_unless($archivo->paquete_id === $paquete->id, 404);
        $this->paquetes->eliminarArchivo($paquete, $archivo, $request->user());

        return response()->json(['message' => 'Archivo eliminado del borrador.', 'data' => $this->detalle($request, $paquete->fresh())]);
    }

    public function validar(Request $request, PaqueteTerritorial $paquete): JsonResponse
    {
        $this->authorize('validar', $paquete);
        $paquete = $this->paquetes->validar($paquete, $request->user());
        $mensaje = $paquete->estado === PaqueteTerritorial::VALIDADO
            ? 'Paquete validado: queda listo para la revisión y la publicación.'
            : 'La validación encontró errores: el paquete sigue en borrador.';

        return response()->json(['message' => $mensaje, 'data' => $this->detalle($request, $paquete)]);
    }

    public function confirmar(Request $request, PaqueteTerritorial $paquete, ValidacionTerritorial $validacion): JsonResponse
    {
        $this->authorize('confirmar', $paquete);
        $this->paquetes->confirmar($paquete, $validacion, $request->user());

        return response()->json(['message' => 'Advertencia confirmada.', 'data' => $this->detalle($request, $paquete->fresh())]);
    }

    public function publicar(Request $request, PaqueteTerritorial $paquete): JsonResponse
    {
        $this->authorize('publicar', $paquete);
        $paquete = $this->paquetes->publicar($paquete, $request->user());

        return response()->json(['message' => "Versión {$paquete->version} publicada. Ya no admite cambios.", 'data' => $this->detalle($request, $paquete)]);
    }

    public function retirar(RetirarPaqueteRequest $request, PaqueteTerritorial $paquete): JsonResponse
    {
        $paquete = $this->paquetes->retirar($paquete, $request->validated('motivo'), $request->user());

        return response()->json(['message' => "Versión {$paquete->version} retirada. Se conserva como historial.", 'data' => $this->detalle($request, $paquete)]);
    }

    public function descargar(PaqueteTerritorial $paquete, ArchivoTerritorial $archivo): StreamedResponse
    {
        $this->authorize('view', $paquete);
        abort_unless($archivo->paquete_id === $paquete->id, 404);
        $disco = Storage::disk(config('territorio.disco'));
        abort_unless($disco->exists($archivo->ruta), 404, 'El archivo ya no está disponible.');

        return $disco->download($archivo->ruta, $archivo->nombre_original);
    }

    private function detalle(Request $request, PaqueteTerritorial $paquete): array
    {
        $paquete->load(['archivos.subidoPor:id,name', 'validaciones.confirmadaPor:id,name', 'creador:id,name', 'publicador:id,name']);
        $usuario = $request->user();
        $validaciones = $paquete->validaciones;

        return [
            'id' => $paquete->id,
            'version' => $paquete->version,
            'estado' => $paquete->estado,
            'notas' => $paquete->notas,
            'creado_por' => $paquete->creador?->name,
            'creado_en' => $paquete->created_at?->toIso8601String(),
            'publicado_por' => $paquete->publicador?->name,
            'publicado_en' => $paquete->publicado_en?->toIso8601String(),
            'retirado_en' => $paquete->retirado_en?->toIso8601String(),
            'motivo_retiro' => $paquete->motivo_retiro,
            'archivos' => $paquete->archivos->map(fn (ArchivoTerritorial $a) => [
                'id' => $a->id,
                'tipo' => $a->tipo,
                'tipo_nombre' => config("territorio.tipos.{$a->tipo}.nombre", $a->tipo),
                'descripcion' => $a->descripcion,
                'capa' => $a->capa,
                'nombre_original' => $a->nombre_original,
                'tamano' => $a->tamano,
                'sha256' => $a->sha256,
                'crs' => $a->crs,
                'fuente' => $a->fuente,
                'licencia' => $a->licencia,
                'fecha_captura' => $a->fecha_captura?->format('Y-m-d'),
                'es_sensible' => $a->es_sensible,
                'subido_por' => $a->subidoPor?->name,
                'subido_en' => $a->created_at?->toIso8601String(),
            ])->values(),
            'validaciones' => $validaciones->map(fn (ValidacionTerritorial $v) => [
                'id' => $v->id,
                'archivo_id' => $v->archivo_id,
                'regla' => $v->regla,
                'resultado' => $v->resultado,
                'detalle' => $v->detalle,
                'requiere_confirmacion' => $v->requiere_confirmacion,
                'confirmada_en' => $v->confirmada_en?->toIso8601String(),
                'confirmada_por' => $v->confirmadaPor?->name,
            ])->values(),
            'bloqueos' => $validaciones->filter->bloquea()->count(),
            'registro' => $paquete->registros()->with('usuario:id,name')->limit(50)->get()->map(fn ($r) => [
                'accion' => $r->accion,
                'detalle' => $r->detalle,
                'usuario' => $r->usuario?->name,
                'fecha' => $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at)->toIso8601String() : null,
            ]),
            'tipos' => collect(config('territorio.tipos'))->map(fn (array $t, string $clave) => ['clave' => $clave, ...$t])->values(),
            'puede' => [
                'subir' => $usuario->can('subir', $paquete),
                'validar' => $usuario->can('validar', $paquete),
                'confirmar' => $usuario->can('confirmar', $paquete),
                'publicar' => $usuario->can('publicar', $paquete),
                'retirar' => $usuario->can('retirar', $paquete),
            ],
        ];
    }
}
