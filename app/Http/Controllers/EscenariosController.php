<?php

namespace App\Http\Controllers;

use App\Models\Escenario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use STS\ZipStream\Facades\Zip;

class EscenariosController extends Controller
{
    private string $storagePath = 'app/public';

    public function index()
    {
        return view('app');
    }

    public function data()
    {
        return response()->json(
            Escenario::query()->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:esceanarios,nombre'],
            'archivo' => ['nullable', 'file', 'max:51200'],
        ]);

        $escenario = Escenario::create([
            'nombre' => $validated['nombre'],
            'estado' => 'Activo',
            'versiones' => $request->hasFile('archivo') ? 1 : 0,
        ]);

        if ($request->hasFile('archivo')) {
            $this->empaquetarArchivos($request->file('archivo'));
        }

        return response()->json([
            'message' => 'Escenario creado exitosamente.',
            'data' => $escenario,
        ], 201);
    }

    public function show(Escenario $escenario)
    {
        return view('app');
    }

    public function update(Request $request, Escenario $escenario)
    {
        return response()->json(['message' => 'Operación aún no implementada.'], 501);
    }

    public function destroy(Escenario $escenario)
    {
        return response()->json(['message' => 'Operación aún no implementada.'], 501);
    }

    private function empaquetarArchivos(object $archivo): void
    {
        $nombreOriginal = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
        $nombreZip = $nombreOriginal.'.zip';
        $directorio = storage_path($this->storagePath);

        File::ensureDirectoryExists($directorio);

        Zip::create($nombreZip, [
            $archivo->getRealPath() => $archivo->getClientOriginalName(),
        ])->saveTo($directorio.DIRECTORY_SEPARATOR.$nombreZip);
    }
}
