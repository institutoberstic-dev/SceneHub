<?php

use App\Http\Controllers\DataRandomController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/firebase/test', function () {
    $database = app('firebase.database');
    $database->getReference('test_connection')->set([
        'mensaje' => 'UnityTracker conectado correctamente 🔥',
        'fecha' => now()->toDateTimeString(),
    ]);
    return response()->json(['status' => 'ok']);
});

