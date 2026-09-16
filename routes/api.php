<?php

use App\Http\Controllers\DataVersionsController;
use App\Http\Controllers\EmocionesController;
use App\Http\Controllers\EscenariosController;
use Illuminate\Support\Facades\Route;

// Public, read-only API: no authentication, permissions or CSRF.
Route::get('/emociones', [EmocionesController::class, 'publicIndex'])->name('emociones.data');
Route::get('/emociones/{meeting}', [EmocionesController::class, 'publicDetail'])->whereNumber('meeting')->name('emociones.detail');
Route::get('/escenarios/{escenario}/resultados-solares', [EscenariosController::class, 'solarResults'])->name('escenarios.solar');
Route::get('/escenarios/{escenario}/resultados', [EscenariosController::class, 'solarResults'])->name('escenarios.results');
Route::get('/escenarios/{escenario}/versiones-datos', [DataVersionsController::class, 'manifest'])->name('datos.versiones');
Route::get('/escenarios/{escenario}/solar', [DataVersionsController::class, 'solar'])->name('datos.solar');
Route::get('/escenarios/{escenario}', [EscenariosController::class, 'detail'])->name('escenarios.detail');
Route::get('/escenarios', [EscenariosController::class, 'data'])->name('escenarios.data');
