<?php

namespace App\Http\Resources\Puente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lista blanca del escenario para el Puente de datos: sin dueño, miembros,
 * correos, nombres de usuarios ni rutas internas.
 *
 * @property \App\Models\Escenario $resource
 */
class EscenarioPuenteResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'numero' => $this->resource->numero,
            'nombre' => $this->resource->nombre,
            'descripcion' => $this->resource->descripcion,
            'tecnologias' => $this->resource->tecnologias->map(fn ($t) => [
                'codigo' => $t->codigo,
                'nombre' => $t->nombre,
                'categoria' => $t->categoria ? ['codigo' => $t->categoria->codigo, 'nombre' => $t->categoria->nombre] : null,
            ])->values(),
            'versiones_datos' => $this->resource->getAttribute('versiones_puente') ?? [],
        ];
    }
}
