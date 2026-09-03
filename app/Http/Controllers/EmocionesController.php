<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;

class EmocionesController extends Controller
{
    /**
     * Muestra el módulo de emociones dentro de la aplicación React.
     */
    public function index()
    {
        return view('app');
    }

    /**
     * Entrega a la aplicación React la misma colección usada por test.blade.php.
     */
    public function data(): JsonResponse
    {
        return response()->json($this->meetingsBL());
    }

    public function meetingsBL()
    {
        $response = Http::timeout(15)->get('https://bersticlive.org/api/meetings');

        return $response->throw()->json();
    }

}
