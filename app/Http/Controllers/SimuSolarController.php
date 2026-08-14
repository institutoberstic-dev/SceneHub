<?php

namespace App\Http\Controllers;

use App\Models\SimuSolar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SimuSolarController extends Controller
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
            SimuSolar::query()->latest()->limit(100)->get()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->all();

            Log::info('Datos recibidos SimuSolar:', $data);

            $datos = [
                'simulationRunning' => (bool) $data['simulationRunning'],
                'inclinacion' => number_format((float) $data['inclinacion'], 5, '.', ''),
                'direccion' => number_format((float) $data['direccion'], 5, '.', ''),
                'latitud' => number_format((float) $data['latitud'], 5, '.', ''),
                'inclinacionOptima' => number_format((float) $data['inclinacionOptima'], 5, '.', ''),
                'estado' => (string) $data['estado'],
                'radiacion' => number_format((float) $data['radiacion'], 5, '.', ''),
                'perdidas' => number_format((float) $data['perdidas'], 5, '.', ''),
                'generacion' => number_format((float) $data['generacion'], 5, '.', ''),
                'conexion' => number_format((float) $data['conexion'], 5, '.', ''),
                'temperatura' => number_format((float) $data['temperatura'], 5, '.', ''),
                'voltaje' => number_format((float) $data['voltaje'], 5, '.', ''),
                'corriente' => number_format((float) $data['corriente'], 5, '.', ''),
                'electrolizacion' => number_format((float) $data['electrolizacion'], 5, '.', ''),
                'bateria' => (int) $data['bateria'],
                'consumo' => number_format((float) $data['consumo'], 5, '.', ''),
            ];

            $SimuSolar = SimuSolar::create($datos);

            Log::info('SimuSolar guardado exitosamente', $datos);

            return response()->json([
                'success' => true,
                'message' => 'Datos guardados correctamente',
                'data' => $SimuSolar,
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Error al guardar SimuSolar: '.$th->getMessage(), [
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
    public function show(SimuSolar $SimuSolar)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SimuSolar $SimuSolar)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SimuSolar $SimuSolar)
    {
        //
    }
}
