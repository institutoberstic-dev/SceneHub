<?php

/*
| Coberturas de la API con token de solo lectura (tabla puente_tokens).
|
| Cada token pertenece a UNA cobertura y solo sirve para las rutas de esa cobertura.
| Se crean con: php artisan puente:token-crear "<nombre>" --alcance=<cobertura> [--dias=N]
|
| Modo de cada cobertura:
|   - opcional:    las rutas responden sin token, igual que antes (Unity y clientes actuales
|                  no se rompen). Si la solicitud trae token, se valida: inválido → 401,
|                  de otra cobertura → 403. Sirve para que cada cliente pruebe su token y
|                  para ver en «Último uso» quién ya lo envía.
|   - obligatorio: sin token válido de esa cobertura → 401.
| «puente» y «territorio» son siempre obligatorias (nunca tuvieron versión pública).
| «capturas» es la única cobertura que admite escritura: solo sirve para POST /api/unity/imagenes.
| «unity» es un token único para la app Unity: vale en simulación, emociones y capturas, con el
| modo que tenga cada una de esas coberturas (no tiene modo propio).
| Cualquier otro valor se trata como «obligatorio» (falla cerrada).
| GET /api/puente/estado acepta un token vigente de cualquier cobertura (para «Probar conexión»).
| Tras cambiar el .env en producción: php artisan config:cache
*/

return [
    'coberturas' => [
        'puente' => [
            'nombre' => 'Puente de datos (app de escritorio): /api/puente/catalogo, /api/puente/escenarios* y /api/puente/territorio/*',
            'modo' => 'obligatorio',
        ],
        'territorio' => [
            'nombre' => 'Paquetes territoriales publicados (PT1): /api/puente/territorio/manifiesto y /archivos/{id}',
            'modo' => 'obligatorio',
        ],
        'simulacion' => [
            'nombre' => 'Escenarios, tecnologías y resultados de simulación (Unity): /api/escenarios*, /api/tecnologias',
            'modo' => env('API_TOKEN_SIMULACION', 'opcional'),
        ],
        'emociones' => [
            'nombre' => 'Datos emocionales por webinar: /api/emociones*',
            'modo' => env('API_TOKEN_EMOCIONES', 'opcional'),
        ],
        'capturas' => [
            'nombre' => 'Carga de capturas desde Unity: POST /api/unity/imagenes (única cobertura con escritura)',
            'modo' => env('API_TOKEN_CAPTURAS', 'opcional'),
            'escritura' => true,
        ],
        'unity' => [
            'nombre' => 'App Unity (un solo token): simulación, emociones y capturas, con el modo de cada una',
            'modo' => null,
        ],
    ],
];
