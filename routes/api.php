<?php

use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\SimuSolarController;
use Illuminate\Support\Facades\Route;

Route::post('/send-features', [FeaturesController::class, 'store'])->name('send-features');
Route::post('/send-simu-sol', [SimuSolarController::class, 'store'])->name('send-simu-sol');

Route::get('/features', [FeaturesController::class, 'data'])->name('features.data');
Route::get('/simu-solars', [SimuSolarController::class, 'data'])->name('simu-solars.data');
Route::get('/escenarios', [\App\Http\Controllers\EscenariosController::class, 'data'])->name('escenarios.data');
Route::get('/users', [\App\Http\Controllers\UserController::class, 'data'])->name('users.data');
Route::get('/roles', [\App\Http\Controllers\RolesController::class, 'data'])->name('roles.data');
