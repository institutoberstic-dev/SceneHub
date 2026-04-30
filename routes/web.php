<?php

use App\Http\Controllers\DataRandomController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\SimuSolarController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('test/',[FeaturesController::class,'index']);
Route::get('test-solar/',[SimuSolarController::class,'index']);

