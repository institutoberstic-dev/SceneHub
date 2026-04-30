<?php

use App\Http\Controllers\CoordinateController;
use App\Http\Controllers\DataRandomController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\SimuSolarController;
use Illuminate\Support\Facades\Route;


// Route::post('/new-location',[DataRandomController::class,'store']);
Route::post('/new-location',[CoordinateController::class,'store']);
Route::post('/send-features',[FeaturesController::class,'store']);
Route::post('/send-simu-sol',[SimuSolarController::class,'store']);
Route::get('/test/api/',[FeaturesController::class,'destroy']);
