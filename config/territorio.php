<?php

/*
|--------------------------------------------------------------------------
| Módulo territorial (paquete del PT1 / GAST)
|--------------------------------------------------------------------------
| SceneHub actúa solo como repositorio versionado: no reproyecta, no
| recalcula, no edita geometrías y no muestra mapas. Estas reglas definen
| qué archivos se aceptan y las validaciones ligeras que se hacen al subirlos.
*/

return [
    // Disco privado: los archivos nunca quedan expuestos en /storage.
    'disco' => 'local',
    'carpeta' => 'territorio',

    'srid' => 9377,
    'capa_malla' => 'malla_hex_1ha_gee',
    'campo_hex' => 'hex_id',

    // Conteos esperados: si no coinciden, es una advertencia que exige confirmación.
    'conteos' => [
        'celdas' => 648,
        'edificaciones' => 91,
    ],

    // Claves mínimas de los JSON (configurables mientras el PT1 confirma el formato).
    'claves_json' => [
        'sitio_json' => ['valores'],
        'georef_json' => [],
    ],

    /*
     | Tipos de archivo. 'max_mb' depende también de upload_max_filesize y
     | post_max_size del servidor (ver docs/puente-anh/PREGUNTAS_PENDIENTES.md, A10).
     */
    'tipos' => [
        'malla_gpkg' => ['nombre' => 'Malla de hexágonos (GeoPackage)', 'obligatorio' => true, 'extensiones' => ['gpkg'], 'firma' => 'sqlite', 'max_mb' => 100],
        'sitio_json' => ['nombre' => 'Valores de sitio (JSON)', 'obligatorio' => true, 'extensiones' => ['json'], 'firma' => 'json', 'max_mb' => 5],
        'escena_glb' => ['nombre' => 'Escena 3D (GLB)', 'obligatorio' => true, 'extensiones' => ['glb'], 'firma' => 'glb', 'max_mb' => 200],
        'georef_json' => ['nombre' => 'Georreferencia de la escena (JSON)', 'obligatorio' => true, 'extensiones' => ['json'], 'firma' => 'json', 'max_mb' => 5],
        'huellas' => ['nombre' => 'Huellas de edificaciones', 'obligatorio' => false, 'extensiones' => ['gpkg', 'geojson', 'json'], 'firma' => 'geo', 'max_mb' => 100],
        'cercas' => ['nombre' => 'Cercas', 'obligatorio' => false, 'extensiones' => ['gpkg', 'geojson', 'json'], 'firma' => 'geo', 'max_mb' => 100],
        'copas' => ['nombre' => 'Copas de árboles', 'obligatorio' => false, 'extensiones' => ['gpkg', 'geojson', 'json'], 'firma' => 'geo', 'max_mb' => 100],
        'superficies' => ['nombre' => 'Superficies', 'obligatorio' => false, 'extensiones' => ['gpkg', 'geojson', 'json'], 'firma' => 'geo', 'max_mb' => 100],
        'corredores' => ['nombre' => 'Corredores', 'obligatorio' => false, 'extensiones' => ['gpkg', 'geojson', 'json'], 'firma' => 'geo', 'max_mb' => 100],
        'infraestructura' => ['nombre' => 'Infraestructura', 'obligatorio' => false, 'extensiones' => ['gpkg', 'geojson', 'json'], 'firma' => 'geo', 'max_mb' => 100],
        'ortofoto' => ['nombre' => 'Ortofoto (solo si su licencia lo permite)', 'obligatorio' => false, 'extensiones' => ['tif', 'tiff', 'jpg', 'jpeg', 'png'], 'firma' => 'imagen', 'max_mb' => 200],
        'otro' => ['nombre' => 'Otro (con descripción)', 'obligatorio' => false, 'extensiones' => ['pdf', 'zip', 'json', 'geojson', 'gpkg', 'csv', 'txt'], 'firma' => 'cualquiera', 'max_mb' => 100],
    ],

    // Tipos que solo admiten un archivo por paquete (subir otro lo reemplaza mientras es borrador).
    'unicos' => ['malla_gpkg', 'sitio_json', 'escena_glb', 'georef_json', 'huellas', 'ortofoto'],
];
