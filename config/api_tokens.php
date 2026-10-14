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
| Cualquier otro valor se trata como «obligatorio» (falla cerrada).
| Tras cambiar el .env en producción: php artisan config:cache
*/

return [
    'coberturas' => [
        'puente' => [
            'nombre' => 'Puente de datos (app de escritorio): /api/puente/v1/*',
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
    ],
];
