<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = TenantContext::id($request);

        if ($tenantId === null) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'An active tenant is required for this request.',
                ], 422);
            }

            return to_route('tenants.index')
                ->withErrors(['tenant' => 'Please select an active tenant.']);
        }

        if ($request->is('api/*')) {
            $headerTenant = trim((string) $request->header('X-Tenant', ''));
            $activeTenantSlug = TenantContext::slug($request);
            $activeTenantId = (string) $tenantId;

            if ($headerTenant === '') {
                return response()->json([
                    'message' => 'X-Tenant header is required.',
                ], 422);
            }

            if (
                $headerTenant !== $activeTenantId
                && $headerTenant !== (string) $activeTenantSlug
            ) {
                return response()->json([
                    'message' => 'X-Tenant does not match the active tenant.',
                ], 403);
            }
        }

        $request->attributes->set('activeTenantId', $tenantId);

        return $next($request);
    }
}
