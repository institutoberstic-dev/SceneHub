<?php

use App\Http\Controllers\DataRandomController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\SimuSolarController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RolesController;
use Illuminate\Support\Facades\Route;

Route::get('test/',[FeaturesController::class,'index']);
Route::get('test-solar/',[SimuSolarController::class,'index']);

Route::get('/users-list',[UserController::class,'list'])->name('users.list');
Route::get('/users-create',[UserController::class,'index'])->name('users.create');
Route::post('/users-register',[UserController::class,'store'])->name('users.register');
Route::put('/users-update/{user}',[UserController::class,'update'])->name('users.update');

Route::get('/roles-list',[RolesController::class,'list'])->name('roles.list');
Route::get('/roles-create',[RolesController::class,'index'])->name('roles.create');
Route::post('/roles-register',[RolesController::class,'store'])->name('roles.register');
Route::put('/roles-update/{rol}',[RolesController::class,'update'])->name('roles.update');
