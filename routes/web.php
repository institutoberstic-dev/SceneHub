<?php

use App\Http\Controllers\DataRandomController;
use App\Http\Controllers\FeaturesController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('test/',[FeaturesController::class,'index']);

