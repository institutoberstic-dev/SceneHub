<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class LoginController extends Controller
{

    public function log_in()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credenciales = $request->only('email','password');
        if (Auth::attempt($credenciales)) {
            if (Auth::user()->status == 1) {
                request()->session()->regenerateToken();

                return redirect()->intended('/escenarios');
                // return response()->json([
                //     'success' => true,
                //     'message' => 'Login exitoso',
                //     'user' => [
                //             'id' => Auth::user()->id,
                //             'name' => Auth::user()->name,
                //             'email' => Auth::user()->email,
                //             'role' => Auth::user()->id_rol_users,
                //             'roleName' => Auth::user()->rol->name_rol
                //     ]
                // ]);
            }

            Auth::logout();

            return response()->json([
                'success' => false,
                'code' => 'USER_DISABLED',
                'message' => 'Usuario deshabilitado!!'
            ], 403);
        }

        return response()->json([
            'success' => false,
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'Usuario y/o contraseña incorrecta.'
        ], 401);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }
}
