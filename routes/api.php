<?php

use App\Http\Controllers\DataRandomController;
use App\Http\Controllers\FeaturesController;
use Illuminate\Support\Facades\Route;


Route::post('/send-features',[FeaturesController::class,'store']);
