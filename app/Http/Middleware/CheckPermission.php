<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $permission = $request->route()->getName();
        $user = auth()->user();

        if (!$user || !$user->can($permission)) {
            return response()->json(['message' => 'Forbidden — missing permission'], 403);
        }

        return $next($request);
    }
}
