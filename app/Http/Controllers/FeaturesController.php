<?php

namespace App\Http\Controllers;

use App\Models\Features;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FeaturesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $features = Features::all();
        dd($features);
        return view('prueba',compact('features'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $features = $request->all();

        $data = [];

        $datos = json_decode($features['data_features'])->features;

        $datos = [
            'corriente' => number_format((float) $datos[0], 5, '.',''),
            'temperatura' => number_format((float) $datos[1], 5, '.',''),
            'presion' => number_format((float) $datos[2], 5, '.',''),
            'eficiencia' => number_format((float) $datos[3], 5, '.',''),
            'voltajeTotal' => number_format((float) $datos[4], 5, '.',''),
            'produccionHidrogeno' => number_format((float) $datos[5], 10, '.',''),
            'temperaturaAmbiente' => number_format((float) $datos[6], 5, '.',''),
            'coefConvectivo' => number_format((float) $datos[7], 5, '.',''),
            'resistenciaInterna' => number_format((float) $datos[8], 5, '.',''),
            'numCeldas' => (int) $datos[9]
        ];

        Log::alert("to send", $datos);

        try {

            Features::create($datos);
            $data = $datos;
            $message = 'success';
        } catch (\Throwable $th) {
            $data = [$th];
            $message = 'error';
        }

        Log::alert($message, $data);
    }

    /**
     * Display the specified resource.
     */
    public function show(Features $features)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Features $features)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Features $features)
    {
        //
    }
}
