<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const LINK_SENT_MESSAGE = 'Si el correo pertenece a una cuenta activa, recibirás un enlace para restablecer tu contraseña. Revisa también la carpeta de spam.';

    private const INVALID_LINK_MESSAGE = 'El enlace no es válido o ya expiró. Solicita uno nuevo.';

    public function showRequestForm()
    {
        return view('app');
    }

    public function showResetForm()
    {
        return view('app');
    }

    /**
     * Envía el enlace de restablecimiento. La respuesta es siempre la misma para
     * no revelar qué correos están registrados o habilitados.
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user && $user->is_active) {
            // El broker limita un envío por correo cada 60 s (config/auth.php → throttle).
            Password::sendResetLink(['email' => $user->email]);
        }

        return response()->json(['message' => self::LINK_SENT_MESSAGE]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'token.required' => self::INVALID_LINK_MESSAGE,
            'email.required' => self::INVALID_LINK_MESSAGE,
            'email.email' => self::INVALID_LINK_MESSAGE,
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (! $user || ! $user->is_active) {
            return $this->invalidLink();
        }

        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return $this->invalidLink();
        }

        return response()->json([
            'message' => 'Tu contraseña fue restablecida. Ya puedes iniciar sesión.',
        ]);
    }

    private function invalidLink(): JsonResponse
    {
        return response()->json([
            'message' => self::INVALID_LINK_MESSAGE,
            'errors' => ['token' => [self::INVALID_LINK_MESSAGE]],
        ], 422);
    }
}
