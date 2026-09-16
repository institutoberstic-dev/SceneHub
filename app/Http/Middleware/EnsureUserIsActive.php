<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tu cuenta está inhabilitada. Contacta al administrador.',
                    'code' => 'ACCOUNT_DISABLED',
                ], 403);
            }

            return redirect()->route('login', ['disabled' => 1]);
        }

        return $next($request);
    }
}
