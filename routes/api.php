<?php

use App\Http\Controllers\DataVersionsController;
use App\Http\Controllers\EmocionesController;
use App\Http\Controllers\EscenariosController;
use Illuminate\Support\Facades\Route;

// Public API: no authentication, permissions, CSRF or rate limiting.
Route::get('/emociones', [EmocionesController::class, 'data'])->name('emociones.data');
Route::post('/emociones/datos', [EmocionesController::class, 'uploadExternal'])->name('emociones.upload.external');
Route::get('/emociones/{meeting}', [EmocionesController::class, 'detail'])->whereNumber('meeting')->name('emociones.detail');
Route::get('/escenarios/{escenario}/resultados-solares', [EscenariosController::class, 'solarResults'])->name('escenarios.solar');
Route::get('/escenarios/{escenario}/versiones-datos', [DataVersionsController::class, 'manifest'])->name('datos.versiones');
Route::get('/escenarios/{escenario}/solar', [DataVersionsController::class, 'solar'])->name('datos.solar');
Route::get('/escenarios/{escenario}', [EscenariosController::class, 'detail'])->name('escenarios.detail');
Route::get('/escenarios', [EscenariosController::class, 'data'])->name('escenarios.data');
