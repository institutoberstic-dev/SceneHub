<?php

use Illuminate\Support\Facades\Route;

// Explicitly retire the global telemetry whose storage migrations were removed.
$retired = fn () => response()->json([
    'code' => 'LEGACY_ENDPOINT_RETIRED',
    'message' => 'Esta API fue retirada. Consulta los datos y versiones del escenario.',
    'endpoints' => [
        'GET /api/escenarios/{id}/versiones-datos?tipo=solar',
        'GET /api/escenarios/{id}/solar',
        'GET /api/escenarios/{id}/emociones-promedio',
    ],
], 410);

Route::post('/send-features', $retired)->name('send-features');
Route::post('/send-simu-sol', $retired)->name('send-simu-sol');
Route::get('/features', $retired)->name('features.data');
Route::get('/simu-solars', $retired)->name('simu-solars.data');
