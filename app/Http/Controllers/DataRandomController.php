<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataRandomController extends Controller
{

    public function store(Request $request)
    {
        $location = $request->all();
        try {
            Location::create($location);
            $data = $location;
            $message = 'Coordenada recibida correctamente';
        } catch (\Throwable $th) {
            $data = [$th];
            $message = 'error';
        }

        Log::alert($message, $data);
    }
}
