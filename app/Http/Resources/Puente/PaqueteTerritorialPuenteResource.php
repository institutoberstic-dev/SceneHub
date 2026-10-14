<?php

namespace App\Http\Resources\Puente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lista blanca del paquete territorial: sin autores, usuarios ni rutas internas.
 *
 * @property \App\Models\PaqueteTerritorial $resource
 */
class PaqueteTerritorialPuenteResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'version' => $this->resource->version,
            'estado' => $this->resource->estado,
            'publicado_en' => $this->resource->publicado_en?->toIso8601String(),
            'notas' => $this->resource->notas,
            'archivos' => $this->resource->archivos->map(fn ($a) => [
                'id' => $a->id,
                'tipo' => $a->tipo,
                'nombre' => $a->nombre_original,
                'tamano' => $a->tamano,
                'sha256' => $a->sha256,
                'crs' => $a->crs,
                'fuente' => $a->fuente,
                'licencia' => $a->licencia,
                'fecha_captura' => $a->fecha_captura?->format('Y-m-d'),
                'es_sensible' => $a->es_sensible,
                'capa' => $a->capa,
                'url_descarga' => '/api/puente/territorio/archivos/'.$a->id,
            ])->values(),
        ];
    }
}
