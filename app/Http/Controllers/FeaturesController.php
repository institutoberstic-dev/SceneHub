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
        return view('app');
    }

    public function data()
    {
        return response()->json(
            Features::query()->latest()->limit(100)->get()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->all();

            Log::info('Datos recibidos:', $data);

            $features = $data['features'];

            $datos = [
                'corriente' => number_format((float) $features[0], 5, '.', ''),
                'temperatura' => number_format((float) $features[1], 5, '.', ''),
                'presion' => number_format((float) $features[2], 5, '.', ''),
                'eficiencia' => number_format((float) $features[3], 5, '.', ''),
                'voltajeTotal' => number_format((float) $features[4], 5, '.', ''),
                'produccionHidrogeno' => number_format((float) $features[5], 10, '.', ''),
                'temperaturaAmbiente' => number_format((float) $features[6], 5, '.', ''),
                'coefConvectivo' => number_format((float) $features[7], 5, '.', ''),
                'resistenciaInterna' => number_format((float) $features[8], 5, '.', ''),
                'numCeldas' => (int) $features[9],
            ];

            Features::create($datos);

            Log::info('Features guardados exitosamente', $datos);

            return response()->json([
                'success' => true,
                'message' => 'Datos guardados correctamente',
                'data' => $datos,
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Error al guardar features: '.$th->getMessage(), [
                'trace' => $th->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar los datos',
                'error' => $th->getMessage(),
            ], 500);
        }
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
        Log::alert('Log get', []);
    }
}
