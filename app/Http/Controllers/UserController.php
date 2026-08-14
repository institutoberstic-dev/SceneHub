<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('app');
    }

    public function list()
    {
        return view('app');
    }

    public function show(User $user)
    {
        return view('app');
    }

    public function data()
    {
        return response()->json(
            User::query()
                ->with('roles:id,name')
                ->latest()
                ->get(['id', 'name', 'email', 'created_at'])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($validated);

        return response()->json([
            'message' => 'Usuario creado exitosamente.',
            'data' => $user->only(['id', 'name', 'email', 'created_at']),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado exitosamente.',
            'data' => $user->only(['id', 'name', 'email', 'created_at']),
        ]);
    }

    public function destroy(User $user)
    {
        return response()->json(['message' => 'Operación aún no implementada.'], 501);
    }
}
