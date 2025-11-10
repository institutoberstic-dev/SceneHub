<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;

class CoordinateController extends Controller
{
    protected $database;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')))
            ->withDatabaseUri(env('FIREBASE_DATABASE_URL'));

        $this->database = $factory->createDatabase();
    }

    public function store(Request $request)
    {
        // ✅ Validar los datos que vienen desde Unity
        $validator = Validator::make($request->all(), [
            'x' => 'required|numeric',
            'y' => 'required|numeric',
            'z' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            Log::warning('❌ Datos inválidos recibidos', ['errors' => $validator->errors()]);
            return response()->json(['error' => 'Datos inválidos'], 400);
        }

        try {
            // ✅ Guardar en SQL Server
            $coord = Location::create($validator->validated());

            // ✅ Subir también a Firebase
            $this->database
                ->getReference('locations/' . $coord->id)
                ->set([
                    'x' => $coord->x,
                    'y' => $coord->y,
                    'z' => $coord->z,
                    'timestamp' => now()->toDateTimeString(),
                ]);

            Log::info('✅ Coordenada guardada en SQL y Firebase', [
                'id' => $coord->id,
                'x' => $coord->x,
                'y' => $coord->y,
                'z' => $coord->z
            ]);

            return response()->json([
                'message' => 'Coordenada registrada correctamente',
                'data' => $coord,
            ], 201);

        } catch (\Exception $e) {
            Log::error('🔥 Error al guardar coordenada', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al guardar coordenada'], 500);
        }
    }
}
