<?php

namespace App\Http\Middleware;

use App\Models\PuenteToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token de solo lectura (Authorization: Bearer …) por cobertura (config/api_tokens.php).
 *
 * - puente.token            → cobertura «puente», siempre obligatoria (/api/puente/v1/*).
 * - api.token:<cobertura>   → «simulacion» o «emociones»; el modo (opcional u obligatorio)
 *                             sale de la configuración (.env).
 *
 * En modo opcional, una solicitud SIN token pasa igual que antes (no rompe a Unity);
 * una solicitud CON token se valida siempre.
 */
class AutenticarTokenPuente
{
    public function handle(Request $request, Closure $next, string $cobertura = 'puente'): Response
    {
        $claro = (string) $request->bearerToken();

        if ($claro === '' && $this->modo($cobertura) === 'opcional') {
            return $next($request);
        }

        $token = $claro !== '' && str_starts_with($claro, PuenteToken::PREFIJO)
            ? PuenteToken::query()->vigentes()->where('token_hash', PuenteToken::hash($claro))->first()
            : null;

        if (! $token) {
            return response()->json(['message' => 'Token no válido, vencido o revocado.'], 401, ['WWW-Authenticate' => 'Bearer']);
        }
        if (! $token->cubre($cobertura)) {
            return response()->json(['message' => 'Este token no cubre esta API.'], 403);
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

    /** «opcional» u «obligatorio». La cobertura «puente» y cualquier valor desconocido son obligatorios. */
    private function modo(string $cobertura): string
    {
        if ($cobertura === 'puente') {
            return 'obligatorio';
        }
        $modo = strtolower(trim((string) config("api_tokens.coberturas.{$cobertura}.modo", 'obligatorio')));

        return $modo === 'opcional' ? 'opcional' : 'obligatorio';
    }
}
