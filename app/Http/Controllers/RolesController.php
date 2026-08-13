<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
            Role::create([
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
    public function show(Role $rol)
    {

        return view('roles.show', compact('rol'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $rol)
    {
        //
        try{
            $rol->update([
                'name' => $request->name,
                'guard_name' => $request->guard_name,
            ]);

            return response()->json(['message' => 'Rol actualizado exitosamente.'], 200);
        }
        catch(\Exception $e){
            return response()->json(['error' => 'Ha ocurrido un error al actualizar el rol.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $rol)
    {
        //
    }

    public function list()
    {
        $roles = Role::all();
        return view('roles.list', compact('roles'));
    }
}
