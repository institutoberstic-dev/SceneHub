<?php

namespace App\Http\Middleware;

use App\Models\Escenario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScenarioAccess
{
    public function handle(Request $request, Closure $next, string ...$levels): Response
    {
        $user = $request->user();
        $scenario = $request->route('escenario');

        abort_unless($user && $scenario instanceof Escenario, 403, 'No tienes acceso a este escenario.');

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        $accessLevel = $scenario->owner_id === $user->id
            ? 'owner'
            : $scenario->users()->where('users.id', $user->id)->value('access_level');

        abort_unless(
            $accessLevel && (empty($levels) || in_array($accessLevel, $levels, true)),
            403,
            'Tu rol dentro de este escenario no permite realizar esta acción.'
        );

        $request->attributes->set('scenario_access_level', $accessLevel);

        return $next($request);
    }
}
