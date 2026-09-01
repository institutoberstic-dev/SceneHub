<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EscenariosController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ResultadosController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/csrf/refresh', function (Request $request) {
    $request->session()->regenerateToken();

    return response()->json(['token' => csrf_token()]);
})->name('csrf.refresh');

Route::redirect('/', '/login');

Route::get('/login', [LoginController::class, 'log_in'])->name('login');
Route::post('/log-in', [LoginController::class, 'login'])->name('login.submit');

Route::middleware('auth')->group(function () {
    Route::post('/log-out', [LoginController::class, 'logout'])->name('logout');
    Route::get('/api/me', [LoginController::class, 'me'])->name('session.user');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/resultados', [ResultadosController::class, 'index'])->name('resultados.index');
    Route::get('/escenarios', [EscenariosController::class, 'index'])->middleware('permission:escenarios.leer')->name('escenarios.index');
    Route::post('/escenarios-store', [EscenariosController::class, 'store'])->middleware('permission:escenarios.crear')->name('escenarios.store');
    Route::put('/escenarios/{escenario}', [EscenariosController::class, 'update'])->middleware('scenario.access:owner,supervisor')->name('escenarios.update');
    Route::post('/escenarios/{escenario}/contenidos', [EscenariosController::class, 'uploadContent'])->middleware('scenario.access:owner')->name('escenarios.contents.store');
    Route::post('/escenarios/{escenario}/members', [EscenariosController::class, 'invite'])->middleware('scenario.access:owner')->name('escenarios.members.store');
    Route::get('/api/escenarios', [EscenariosController::class, 'data'])->middleware('permission:escenarios.leer')->name('escenarios.data');

    Route::middleware('role:admin')->group(function () {
        Route::get('/users-list', [UserController::class, 'list'])->name('users.list');
        Route::get('/users-create', [UserController::class, 'index'])->name('users.create');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('/users-register', [UserController::class, 'store'])->name('users.register');
        Route::put('/users-update/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('/api/users', [UserController::class, 'data'])->name('users.data');

        Route::get('/roles-list', [RolesController::class, 'list'])->name('roles.list');
        Route::get('/roles-create', [RolesController::class, 'index'])->name('roles.create');
        Route::get('/roles/{rol}', [RolesController::class, 'show'])->name('roles.show');
        Route::post('/roles-register', [RolesController::class, 'store'])->name('roles.register');
        Route::put('/roles-update/{rol}', [RolesController::class, 'update'])->name('roles.update');
        Route::get('/api/roles', [RolesController::class, 'data'])->name('roles.data');
    });
});

// Permite recargar o abrir directamente cualquier ruta administrada por React Router.
Route::view('/{any}', 'app')
    ->where('any', '^(?!api(?:/|$)).*')
    ->middleware('auth')
    ->name('spa.fallback');
