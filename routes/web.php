<?php

use App\Http\Controllers\DataRandomController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/data-random',[DataRandomController::class,'data_random']);
