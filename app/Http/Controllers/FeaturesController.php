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
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        $features = $request->input('features');

        // $datos = json_decode($features['data_features'])->features;

        Log::alert('to send:', $data);

        $datos = [
            'corriente' => number_format((float) $features[0], 5, '.',''),
            'temperatura' => number_format((float) $features[1], 5, '.',''),
            'presion' => number_format((float) $features[2], 5, '.',''),
            'eficiencia' => number_format((float) $features[3], 5, '.',''),
            'voltajeTotal' => number_format((float) $features[4], 5, '.',''),
            'produccionHidrogeno' => number_format((float) $features[5], 10, '.',''),
            'temperaturaAmbiente' => number_format((float) $features[6], 5, '.',''),
            'coefConvectivo' => number_format((float) $features[7], 5, '.',''),
            'resistenciaInterna' => number_format((float) $features[8], 5, '.',''),
            'numCeldas' => (int) $features[9]
        ];


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
