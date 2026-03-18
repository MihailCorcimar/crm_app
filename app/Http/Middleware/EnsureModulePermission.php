<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModulePermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $module, string $action = 'read'): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasModulePermission($module, $action)) {
            abort(403, 'Sem permissões para aceder a este módulo.');
        }

        return $next($request);
    }
}

