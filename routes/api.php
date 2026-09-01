<?php

use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\SimuSolarController;
use Illuminate\Support\Facades\Route;

Route::post('/send-features', [FeaturesController::class, 'store'])->name('send-features');
Route::post('/send-simu-sol', [SimuSolarController::class, 'store'])->name('send-simu-sol');

Route::get('/features', [FeaturesController::class, 'data'])->name('features.data');
Route::get('/simu-solars', [SimuSolarController::class, 'data'])->name('simu-solars.data');
