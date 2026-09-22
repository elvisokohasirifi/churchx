<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupAdministrator
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = backpack_auth()->user();

        abort_unless(
            $user instanceof User
            && ($user->hasActiveRole('App Administrator') || $user->hasActiveRole('Church Administrator')),
            403,
        );

        return $next($request);
    }
}
