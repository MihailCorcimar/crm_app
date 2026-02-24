<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use App\Support\TenantSubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenantId = TenantContext::id($request);
        if ($tenantId === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'An active tenant is required.',
                ], 422);
            }

            return to_route('tenants.index');
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            abort(404);
        }

        $hasFeature = app(TenantSubscriptionService::class)->hasFeature(
            $tenant,
            $feature,
            $request->user()
        );

        if (! $hasFeature) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Current plan does not include this feature.',
                ], 403);
            }

            return to_route('tenants.billing.show')
                ->withErrors(['plan' => 'Current plan does not include this feature.']);
        }

        return $next($request);
    }
}
