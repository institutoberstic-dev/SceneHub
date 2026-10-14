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
Route::middleware('api.token:emociones')->group(function () {
    Route::get('/emociones', [EmocionesController::class, 'publicIndex'])->name('emociones.data');
    Route::get('/emociones/{meeting}', [EmocionesController::class, 'publicDetail'])->whereNumber('meeting')->name('emociones.detail');
});

Route::middleware('api.token:simulacion')->group(function () {
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

// Public upload API for Unity: no authentication or CSRF. Stores images in
// storage/app/public/unity/{escenario_id|general}/{YYYY-MM-DD}. Limited to 30 requests per minute per IP.
Route::post('/unity/imagenes', [UnityImagenesController::class, 'store'])->middleware('throttle:30,1')->name('unity.imagenes.store');

// API de SOLO LECTURA para la app de escritorio «Puente de datos». Exige token
// (php artisan puente:token-crear). No modifica ni reemplaza las rutas públicas de arriba.
Route::prefix('puente/v1')->middleware(['puente.token', 'throttle:120,1'])->name('puente.')->group(function () {
    Route::get('/estado', [PuenteController::class, 'estado'])->name('estado');
    Route::get('/catalogo-variables', [PuenteController::class, 'catalogoVariables'])->name('catalogo');
    Route::get('/escenarios', [PuenteController::class, 'escenarios'])->name('escenarios');
    Route::get('/escenarios/{escenario}/versiones/{version}/archivo', [PuenteController::class, 'archivoVersion'])->whereNumber('escenario')->name('escenarios.archivo');
    Route::get('/territorio/paquetes', [PuenteController::class, 'paquetesTerritoriales'])->name('territorio.paquetes');
    Route::get('/territorio/archivos/{archivo}', [PuenteController::class, 'archivoTerritorial'])->whereNumber('archivo')->name('territorio.archivo');
});
