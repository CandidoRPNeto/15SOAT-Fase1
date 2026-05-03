<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowed = array_map(fn ($r) => UserRole::from($r), $roles);

        if (! $user->hasRole(...$allowed)) {
            return response()->json(['message' => 'Acesso não autorizado para este perfil.'], 403);
        }

        return $next($request);
    }
}
