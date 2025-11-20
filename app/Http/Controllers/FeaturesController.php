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
         try {
            // Obtener todo el contenido JSON
            $data = $request->all();

            Log::info('Datos recibidos:', $data);

            // Verificar que 'features' existe y es un array
            if (!isset($data['features']) || !is_array($data['features'])) {
                Log::error('Features no encontrado o no es array', $data);
                return response()->json([
                    'success' => false,
                    'message' => 'El campo features es requerido y debe ser un array'
                ], 400);
            }

            $features = $data['features'];

            // Verificar que tiene suficientes elementos
            if (count($features) < 10) {
                Log::error('Features insuficientes', ['count' => count($features)]);
                return response()->json([
                    'success' => false,
                    'message' => 'Se requieren al menos 10 valores en features'
                ], 400);
            }

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

            Features::create($datos);

            Log::info('Features guardados exitosamente', $datos);

            return response()->json([
                'success' => true,
                'message' => 'Datos guardados correctamente',
                'data' => $datos
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Error al guardar features: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar los datos',
                'error' => $th->getMessage()
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
        Log::alert("Log get", []);
    }
}
