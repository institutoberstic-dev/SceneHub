<?php

namespace App\Http\Controllers;

use App\Models\Escenario;
use Illuminate\Http\Request;
use STS\ZipStream\Facades\Zip; // Asegúrate de importar la fachada oficial del paquete
use Illuminate\Support\Facades\Storage;

class EscenariosController extends Controller
{
    private $storagePath = 'app/public'; // Ruta de almacenamiento de archivos

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $escenarios = Escenario::all();

        return view('escenarios.index', compact('escenarios'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validar los datos del formulario
        $request->validate([
            'nombre' => 'required|string|max:255',
            // 'archivo' => 'required|file|mimes:txt,csv,zip', // Asegúrate de permitir solo tipos de archivo válidos
        ]);

        // Crear un nuevo escenario
        $escenario = new Escenario();
        $escenario->nombre = $request->input('nombre');
        $escenario->estado = 'activo'; // Puedes ajustar esto según tus necesidades
        $escenario->versiones = $request->hasFile('archivo') ? 1 : 0;

        // Guardar el escenario en la base de datos
        $escenario->save();

        // Manejar el archivo subido
        if ($request->hasFile('archivo')) {
            $archivo = $request->file('archivo');

            // Empaquetar el archivo en un ZIP y guardarlo en el almacenamiento
            $this->empaquetarArchivos($archivo);
        }

        return redirect()->route('escenarios.index')->with('success', 'Escenario creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Escenario $escenario)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Escenario $escenario)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Escenario $escenario)
    {
        //
    }

	public function empaquetarArchivos(object $archivo)
    {
        // 1. Obtener el nombre limpio del archivo original sin su extensión
        $nombreOriginal = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);

        // 2. Definir el nombre del contenedor final con la extensión .zip
        $nombreZip = $nombreOriginal . '.zip';

        // 3. Ruta absoluta en tu servidor donde se guardará físicamente el ZIP
        $rutaDestinoZip = storage_path($this->getStoragePath() . '/' . $nombreZip);

        // 4. Creamos el ZIP agregando el archivo cargado y lo guardamos directamente en el disco
        Zip::create($nombreZip, [
            // Pasamos el archivo temporal real que el usuario acaba de subir
            $archivo->getRealPath() => $archivo->getClientOriginalName()
        ])->saveTo($rutaDestinoZip);

        // Opcional: retornar una respuesta JSON o la ruta para confirmar que se guardó
        return response()->json([
            'mensaje' => 'Archivo comprimido y almacenado con éxito',
            'ruta' => $rutaDestinoZip
        ], 200);
    }


    public function getStoragePath()
    {
        return $this->storagePath;
    }
}
