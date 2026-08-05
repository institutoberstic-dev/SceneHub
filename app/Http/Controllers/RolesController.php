<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $permisos = Permission::all();
        return view('roles.create', compact('permisos'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        try{
            Rol::create([
                'name' => $request->name,
                'guard_name' => $request->guard_name,
            ]);

            return response()->json(['message' => 'Rol creado exitosamente.'], 201);
        }
        catch(\Exception $e){
            return response()->json(['error' => 'Ha ocurrido un error al crear el rol.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Rol $rol)
    {

        return view('roles.show', compact('rol'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Rol $rol)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Rol $rol)
    {
        //
    }

    public function list()
    {
        $roles = Rol::all();
        return view('roles.list', compact('roles'));
    }
}
