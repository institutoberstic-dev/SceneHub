<?php

namespace Database\Seeders;

use App\Models\CategoriaTecnologia;
use App\Models\Tecnologia;
use Illuminate\Database\Seeder;

/**
 * Catálogo de tecnologías participantes en los escenarios de simulación,
 * agrupadas por categoría.
 *
 * Es idempotente: se puede ejecutar en cada despliegue con
 * `php artisan db:seed --class=TecnologiasSeeder` sin duplicar registros.
 * Los códigos (de tecnología y de categoría) son el contrato con Unity: no se
 * deben renombrar. Los nombres visibles sí pueden corregirse aquí sin romper
 * la integración.
 */
class TecnologiasSeeder extends Seeder
{
    public const CATEGORIAS = [
        ['codigo' => 'GENERACION_ENERGIA', 'nombre' => 'Generación de energía'],
        ['codigo' => 'TRATAMIENTO_AGUA', 'nombre' => 'Tratamiento de agua'],
        ['codigo' => 'HIDROGENO_AMONIACO', 'nombre' => 'Hidrógeno y amoniaco'],
        ['codigo' => 'VALORIZACION_RESIDUOS', 'nombre' => 'Valorización de residuos'],
    ];

    /** El orden de la lista es el orden en que se muestran en el formulario. */
    public const CATALOGO = [
        ['codigo' => 'PANEL_SOLAR', 'nombre' => 'Paneles solares', 'categoria' => 'GENERACION_ENERGIA'],
        ['codigo' => 'TURBINAS_EOLICAS', 'nombre' => 'Turbinas eólicas', 'categoria' => 'GENERACION_ENERGIA'],
        ['codigo' => 'DIESEL', 'nombre' => 'Generador diésel', 'categoria' => 'GENERACION_ENERGIA'],
        ['codigo' => 'DESALINIZADORA', 'nombre' => 'Desalinizadora', 'categoria' => 'TRATAMIENTO_AGUA'],
        ['codigo' => 'ELECTROLIZADORA', 'nombre' => 'Electrolizadora', 'categoria' => 'HIDROGENO_AMONIACO'],
        ['codigo' => 'HABER-BOSH', 'nombre' => 'Reactor Haber-Bosch', 'categoria' => 'HIDROGENO_AMONIACO'],
        ['codigo' => 'GEOPOLIMEROS', 'nombre' => 'Planta de valorización de geopolímeros', 'categoria' => 'VALORIZACION_RESIDUOS'],
        ['codigo' => 'MINERIA_LIQUIDA', 'nombre' => 'Planta de valorización de salmuera', 'categoria' => 'VALORIZACION_RESIDUOS'],
    ];

    public function run(): void
    {
        $categorias = [];
        foreach (self::CATEGORIAS as $orden => $categoria) {
            $categorias[$categoria['codigo']] = CategoriaTecnologia::updateOrCreate(
                ['codigo' => $categoria['codigo']],
                ['nombre' => $categoria['nombre'], 'orden' => $orden + 1],
            )->id;
        }

        foreach (self::CATALOGO as $orden => $tecnologia) {
            Tecnologia::updateOrCreate(
                ['codigo' => $tecnologia['codigo']],
                [
                    'nombre' => $tecnologia['nombre'],
                    'categoria_id' => $categorias[$tecnologia['categoria']],
                    'orden' => $orden + 1,
                    'activo' => true,
                ],
            );
        }
    }
}
