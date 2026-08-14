<?php

use App\Http\Controllers\CacheController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EscenariosController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ResultadosController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SincronizacionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VersionesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/csrf/refresh', function (Request $request) {
    $request->session()->regenerateToken();

    return response()->json(['token' => csrf_token()]);
})->name('csrf.refresh');

Route::redirect('/', '/login');

Route::get('/login', [LoginController::class, 'log_in'])->name('login.view');
Route::post('/log-in', [LoginController::class, 'login'])->name('login');
Route::post('/log-out', [LoginController::class, 'logout'])->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/resultados', [ResultadosController::class, 'index'])->name('resultados.index');
Route::get('/versiones', [VersionesController::class, 'index'])->name('versiones.index');
Route::get('/sincronizacion', [SincronizacionController::class, 'index'])->name('sincronizacion.index');
Route::get('/cache-local', [CacheController::class, 'index'])->name('cache.index');

Route::get('/escenarios', [EscenariosController::class, 'index'])->name('escenarios.index');
Route::post('/escenarios-store', [EscenariosController::class, 'store'])->name('escenarios.store');

Route::get('/users-list', [UserController::class, 'list'])->name('users.list');
Route::get('/users-create', [UserController::class, 'index'])->name('users.create');
Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
Route::post('/users-register', [UserController::class, 'store'])->name('users.register');
Route::put('/users-update/{user}', [UserController::class, 'update'])->name('users.update');

Route::get('/roles-list', [RolesController::class, 'list'])->name('roles.list');
Route::get('/roles-create', [RolesController::class, 'index'])->name('roles.create');
Route::get('/roles/{rol}', [RolesController::class, 'show'])->name('roles.show');
Route::post('/roles-register', [RolesController::class, 'store'])->name('roles.register');
Route::put('/roles-update/{rol}', [RolesController::class, 'update'])->name('roles.update');
