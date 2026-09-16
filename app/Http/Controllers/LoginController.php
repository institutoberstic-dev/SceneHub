<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function log_in()
    {
        return view('app');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Usuario y/o contraseña incorrecta.',
            ], 401);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_DISABLED',
                'message' => 'Tu cuenta está inhabilitada. Contacta al administrador.',
            ], 403);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'user' => [
                ...Auth::user()->only(['id', 'name', 'email', 'is_active']),
                'roles' => Auth::user()->getRoleNames()->values(),
                'permissions' => Auth::user()->getAllPermissions()->pluck('name')->values(),
            ],
            'redirect' => route('dashboard.index'),
        ]);
    }

    public function me()
    {
        $user = Auth::user();

        return response()->json([
            ...$user->only(['id', 'name', 'email', 'is_active']),
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

}
