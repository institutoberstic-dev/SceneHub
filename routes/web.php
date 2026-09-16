<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmocionesController;
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

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/session-user', [LoginController::class, 'me'])->name('session.user');
    Route::post('/log-out', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/escenarios-data', [EscenariosController::class, 'data'])->middleware('permission:escenarios.leer')->name('escenarios.data.web');
    Route::get('/escenarios-data/{escenario}', [EscenariosController::class, 'detail'])->middleware(['permission:escenarios.leer', 'scenario.access'])->name('escenarios.detail.web');
    Route::get('/escenarios-data/{escenario}/resultados-solares', [EscenariosController::class, 'solarResults'])->middleware(['permission:escenarios.leer', 'scenario.access'])->name('escenarios.solar.web');
    Route::get('/emociones-data', [EmocionesController::class, 'data'])->middleware('permission:emociones.leer')->name('emociones.data.web');
    Route::get('/emociones-data/{meeting}', [EmocionesController::class, 'detail'])->whereNumber('meeting')->middleware('permission:emociones.leer')->name('emociones.detail.web');
    Route::post('/emociones-data', [EmocionesController::class, 'uploadExternal'])->middleware('permission:emociones.cargar')->name('emociones.upload.web');
    Route::get('/resultados', [ResultadosController::class, 'index'])->middleware('permission:escenarios.leer')->name('resultados.index');
    Route::get('/emociones', [EmocionesController::class, 'index'])->middleware('permission:emociones.leer')->name('emociones.index');
    Route::get('/emociones/{meeting}', [EmocionesController::class, 'index'])->whereNumber('meeting')->middleware('permission:emociones.leer')->name('emociones.show');
    Route::post('/emociones/{meeting}/miembros', [EmocionesController::class, 'invite'])->whereNumber('meeting')->middleware('permission:emociones.invitar')->name('emociones.members.store');
    Route::delete('/emociones/{meeting}/miembros/{user}', [EmocionesController::class, 'removeMember'])->whereNumber('meeting')->middleware('permission:emociones.invitar')->name('emociones.members.destroy');
    Route::get('/escenarios', [EscenariosController::class, 'index'])->middleware('permission:escenarios.leer')->name('escenarios.index');
    Route::get('/escenarios/{escenario}', [EscenariosController::class, 'show'])->middleware(['permission:escenarios.leer', 'scenario.access'])->name('escenarios.show');
    Route::post('/escenarios-store', [EscenariosController::class, 'store'])->middleware('permission:escenarios.crear')->name('escenarios.store');
    Route::put('/escenarios/{escenario}', [EscenariosController::class, 'update'])->middleware(['permission:escenarios.actualizar', 'scenario.access:owner,supervisor'])->name('escenarios.update');
    Route::post('/escenarios/{escenario}/contenidos', [EscenariosController::class, 'uploadContent'])->middleware(['permission:escenarios.versionar', 'scenario.access:owner'])->name('escenarios.contents.store');
    Route::post('/escenarios/{escenario}/members', [EscenariosController::class, 'invite'])->middleware(['permission:escenarios.invitar', 'scenario.access:owner'])->name('escenarios.members.store');
    Route::delete('/escenarios/{escenario}/members/{user}', [EscenariosController::class, 'removeMember'])->middleware(['permission:escenarios.invitar', 'scenario.access:owner'])->name('escenarios.members.destroy');
    Route::get('/escenarios/{escenario}/contenidos/{contenido}/download', [EscenariosController::class, 'downloadContent'])->middleware(['permission:archivos.leer', 'scenario.access'])->name('escenarios.contents.download');
    Route::get('/escenarios/{escenario}/download', [EscenariosController::class, 'download'])->middleware(['permission:archivos.leer', 'scenario.access'])->name('escenarios.download');
    Route::delete('/escenarios/{escenario}', [EscenariosController::class, 'destroy'])->middleware('permission:escenarios.eliminar')->name('escenarios.destroy');

    Route::middleware('role:admin')->group(function () {
        Route::get('/users-data', [UserController::class, 'data'])->name('users.data');
        Route::get('/users-list', [UserController::class, 'list'])->name('users.list');
        Route::get('/users-create', [UserController::class, 'index'])->name('users.create');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('/users-register', [UserController::class, 'store'])->name('users.register');
        Route::put('/users-update/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('/roles-data', [RolesController::class, 'data'])->name('roles.data');
        Route::get('/roles-list', [RolesController::class, 'list'])->name('roles.list');
        Route::get('/roles/{rol}', [RolesController::class, 'show'])->name('roles.show');
    });
});

// Permite recargar o abrir directamente cualquier ruta administrada por React Router.
Route::view('/{any}', 'app')
    ->where('any', '^(?!api(?:/|$)).*')
    ->middleware(['auth', 'active'])
    ->name('spa.fallback');
