<?php

namespace App\Http\Middleware;

use App\Models\PuenteToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige un token de solo lectura (Authorization: Bearer …) para /api/puente/v1/*.
 * Las rutas públicas existentes (las que usa Unity) NO pasan por aquí.
 */
class AutenticarTokenPuente
{
    public function handle(Request $request, Closure $next): Response
    {
        $claro = (string) $request->bearerToken();
        $token = $claro !== '' && str_starts_with($claro, PuenteToken::PREFIJO)
            ? PuenteToken::query()->vigentes()->where('token_hash', PuenteToken::hash($claro))->first()
            : null;

        if (! $token) {
            return response()->json(['message' => 'Token no válido, vencido o revocado.'], 401, ['WWW-Authenticate' => 'Bearer']);
        }
        if (! $request->isMethodSafe()) {
            return response()->json(['message' => 'Este token es de solo lectura.'], 405);
        }
        if (! $token->ultimo_uso_en || $token->ultimo_uso_en->lt(now()->subMinute())) {
            $token->forceFill(['ultimo_uso_en' => now()])->saveQuietly();
        }
        $request->attributes->set('puente_token_id', $token->id);

        $respuesta = $next($request);
        $respuesta->headers->set('Cache-Control', 'private, no-store');

        return $respuesta;
    }
}
