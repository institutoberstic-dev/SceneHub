<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function data()
    {
        return response()->json(Role::withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']));
    }

    public function index()
    {
        return view('app');
    }

    public function list()
    {
        return view('app');
    }

    public function show(Role $rol)
    {
        return view('app');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')],
            'guard_name' => ['required', 'string', 'max:255'],
        ]);

        $role = Role::create($validated);

        return response()->json([
            'message' => 'Rol creado exitosamente.',
            'data' => $role,
        ], 201);
    }

    public function update(Request $request, Role $rol)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($rol->id)],
            'guard_name' => ['required', 'string', 'max:255'],
        ]);

        $rol->update($validated);

        return response()->json([
            'message' => 'Rol actualizado exitosamente.',
            'data' => $rol,
        ]);
    }

    public function destroy(Role $rol)
    {
        return response()->json(['message' => 'Operación aún no implementada.'], 501);
    }
}
