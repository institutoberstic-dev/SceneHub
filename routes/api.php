<?php

use App\Http\Controllers\Api\PuenteController;
use App\Http\Controllers\DataVersionsController;
use App\Http\Controllers\EmocionesController;
use App\Http\Controllers\EscenariosController;
use App\Http\Controllers\TecnologiasController;
use App\Http\Controllers\UnityImagenesController;
use Illuminate\Support\Facades\Route;

// API pública de solo lectura (Unity y clientes externos), sin sesión, permisos ni CSRF.
// Cada grupo tiene su cobertura de token (config/api_tokens.php). En modo «opcional» responde
// igual que antes sin token; en modo «obligatorio» (API_TOKEN_* en .env) exige un token de esa cobertura:
// php artisan puente:token-crear "<nombre>" --alcance=emociones|simulacion
// El token único de la app Unity (cobertura «unity») vale en los tres grupos.
Route::middleware('api.token:emociones,unity')->group(function () {
    Route::get('/emociones', [EmocionesController::class, 'publicIndex'])->name('emociones.data');
    Route::get('/emociones/{meeting}', [EmocionesController::class, 'publicDetail'])->whereNumber('meeting')->name('emociones.detail');
});

Route::middleware('api.token:simulacion,unity')->group(function () {
    Route::get('/escenarios/{escenario}/resultados', [EscenariosController::class, 'simulationResults'])->name('escenarios.results');
    Route::get('/escenarios/{escenario}/resultados-simulacion', [EscenariosController::class, 'simulationResults'])->name('escenarios.simulation');
    Route::get('/escenarios/{escenario}/simulacion', [DataVersionsController::class, 'simulation'])->name('datos.simulacion');
    // Alias históricos que Unity ya consume: responden igual que las rutas nuevas.
    Route::get('/escenarios/{escenario}/resultados-solares', [EscenariosController::class, 'simulationResults'])->name('escenarios.solar');
    Route::get('/escenarios/{escenario}/solar', [DataVersionsController::class, 'simulation'])->name('datos.solar');
    Route::get('/escenarios/{escenario}/versiones-datos', [DataVersionsController::class, 'manifest'])->name('datos.versiones');
    Route::get('/escenarios/{escenario}', [EscenariosController::class, 'detail'])->name('escenarios.detail');
    Route::get('/tecnologias', [TecnologiasController::class, 'publicIndex'])->name('tecnologias.catalogo');
    Route::get('/escenarios', [EscenariosController::class, 'data'])->name('escenarios.data');
});

// Carga de capturas desde Unity, sin sesión ni CSRF. Guarda en
// storage/app/public/unity/{escenario_id|general}/{AAAA-MM-DD}. Máximo 30 solicitudes por minuto por IP.
// Cobertura «capturas» (o el token «unity»): en modo opcional acepta envíos sin token, como antes;
// con API_TOKEN_CAPTURAS=obligatorio exige token.
Route::post('/unity/imagenes', [UnityImagenesController::class, 'store'])
    ->middleware(['throttle:30,1', 'api.token:capturas,unity'])->name('unity.imagenes.store');

// --- API de SOLO LECTURA para la app de escritorio «Puente de datos» (ajuste 7 del encargo):
// escenarios, catálogo, Excel original, manifiesto territorial y descarga de archivos.
// Va en su propio espacio /api/puente/* para no mezclarse con las rutas públicas de Unity.
// Tokens: php artisan puente:token-crear "<nombre>" --alcance=puente|territorio
Route::prefix('puente')->middleware('throttle:120,1')->name('puente.')->group(function () {
    // «Probar conexión»: acepta un token vigente de cualquier cobertura.
    Route::get('/estado', [PuenteController::class, 'estado'])
        ->middleware('api.token:puente,territorio,simulacion,emociones')->name('estado');

    // Cobertura «puente» (siempre obligatoria).
    Route::middleware('api.token:puente')->group(function () {
        Route::get('/catalogo', [PuenteController::class, 'catalogoVariables'])->name('catalogo');
        Route::get('/escenarios', [PuenteController::class, 'escenarios'])->name('escenarios');
        Route::get('/escenarios/{escenario}/versiones/{version}/excel', [PuenteController::class, 'archivoVersion'])->whereNumber('escenario')->name('escenarios.excel');
    });

    // Paquetes territoriales PUBLICADOS (PT1): cobertura «territorio» o «puente» (siempre obligatoria).
    Route::prefix('territorio')->middleware('api.token:territorio,puente')->name('territorio.')->group(function () {
        Route::get('/manifiesto', [PuenteController::class, 'paquetesTerritoriales'])->name('manifiesto');
        Route::get('/archivos/{archivo}', [PuenteController::class, 'archivoTerritorial'])->whereNumber('archivo')->name('archivo');
    });
});
