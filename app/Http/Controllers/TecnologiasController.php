<?php

namespace App\Http\Controllers;

use App\Models\Tecnologia;

class TecnologiasController extends Controller
{
    /** Catálogo para los formularios de escenarios: solo tecnologías activas, con su categoría. */
    public function index()
    {
        return response()->json(
            $this->catalog(Tecnologia::query()->activas())->map(fn (Tecnologia $tecnologia) => $this->present($tecnologia))->values()
        );
    }

    /** Catálogo público para Unity: incluye las inactivas para que pueda reconocer códigos históricos. */
    public function publicIndex()
    {
        return response()->json(
            $this->catalog(Tecnologia::query())->map(fn (Tecnologia $tecnologia) => [
                ...$this->present($tecnologia),
                'activo' => $tecnologia->activo,
            ])->values()
        );
    }

    private function catalog($query)
    {
        return $query->with('categoria')->ordenadas()->get();
    }

    private function present(Tecnologia $tecnologia): array
    {
        return [
            'codigo' => $tecnologia->codigo,
            'nombre' => $tecnologia->nombre,
            'categoria' => $tecnologia->categoria
                ? ['codigo' => $tecnologia->categoria->codigo, 'nombre' => $tecnologia->categoria->nombre]
                : null,
        ];
    }
}
