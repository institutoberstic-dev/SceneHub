<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\EscenariosController;
use Illuminate\Support\Facades\Route;


/**
 * Token
 */


Route::get('/csrf/refresh', function (Request $request) {
    $request->session()->regenerateToken();

    return response()->json([
        'token' => csrf_token(),
    ]);
});

Route::redirect('/', '/login');

Route::get('/login', [LoginController::class, 'log_in']);
Route::post('/log-in', [LoginController::class, 'login'])->name('login');

Route::get('/users-list',[UserController::class,'list'])->name('users.list');
Route::get('/users-create',[UserController::class,'index'])->name('users.create');
Route::post('/users-register',[UserController::class,'store'])->name('users.register');
Route::put('/users-update/{user}',[UserController::class,'update'])->name('users.update');

Route::get('/roles-list',[RolesController::class,'list'])->name('roles.list');
Route::get('/roles-create',[RolesController::class,'index'])->name('roles.create');
Route::post('/roles-register',[RolesController::class,'store'])->name('roles.register');
Route::put('/roles-update/{rol}',[RolesController::class,'update'])->name('roles.update');

Route::get('/dashboard',[DashboardController::class,'index'])->name('dashboard.index');
Route::get('/escenarios',[EscenariosController::class,'index'])->name('escenarios.index');
Route::post('/escenarios-store',[EscenariosController::class,'store'])->name('escenarios.store');
