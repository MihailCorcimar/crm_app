<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user === null
            || ! Schema::hasTable('tenants')
            || ! Schema::hasTable('tenant_user')
            || ! Schema::hasColumn('users', 'current_tenant_id')
        ) {
            return $next($request);
        }

        $tenants = $user->tenants()
            ->select('tenants.id', 'tenants.name', 'tenants.slug')
            ->orderBy('tenants.name')
            ->get();

        $preferredTenantId = (int) ($user->current_tenant_id ?? 0);
        $activeTenant = $this->resolveActiveTenant($request, $tenants, $preferredTenantId);
        $tenantColors = $this->tenantPrimaryColors($tenants);

        if ($activeTenant === null) {
            $request->session()->forget('current_tenant_id');

            if ($user->current_tenant_id !== null) {
                $user->forceFill(['current_tenant_id' => null])->save();
            }
        } else {
            $request->session()->put('current_tenant_id', $activeTenant->id);

            $hasValidPreference = $preferredTenantId > 0 && $tenants->contains('id', $preferredTenantId);

            if (! $hasValidPreference) {
                $user->forceFill(['current_tenant_id' => $activeTenant->id])->save();
            }
        }

        $request->attributes->set('tenantContext', [
            'active' => $activeTenant !== null
                ? $this->tenantPayload($activeTenant, $tenantColors)
                : null,
            'available' => $tenants
                ->map(fn (Tenant $tenant): array => $this->tenantPayload($tenant, $tenantColors))
                ->values()
                ->all(),
        ]);

        return $next($request);
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     */
    private function resolveActiveTenant(Request $request, Collection $tenants, int $preferredTenantId): ?Tenant
    {
        if ($tenants->isEmpty()) {
            return null;
        }

        $sessionTenantId = (int) $request->session()->get('current_tenant_id', 0);

        if ($sessionTenantId > 0) {
            $fromSession = $tenants->firstWhere('id', $sessionTenantId);

            if ($fromSession !== null) {
                return $fromSession;
            }
        }

        if ($preferredTenantId > 0) {
            $fromPreference = $tenants->firstWhere('id', $preferredTenantId);

            if ($fromPreference !== null) {
                return $fromPreference;
            }
        }

        return $tenants->first();
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<int, string>
     */
    private function tenantPrimaryColors(Collection $tenants): array
    {
        if ($tenants->isEmpty() || ! Schema::hasTable('tenant_settings')) {
            return [];
        }

        /** @var array<int, mixed> $rawSettings */
        $rawSettings = TenantSetting::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->pluck('settings', 'tenant_id')
            ->all();

        $colors = [];

        foreach ($rawSettings as $tenantId => $settings) {
            $rawColor = data_get($settings, 'brand_primary_color');
            $colors[(int) $tenantId] = $this->normalizePrimaryColor(is_string($rawColor) ? $rawColor : null);
        }

        return $colors;
    }

    /**
     * @param  array<int, string>  $tenantColors
     * @return array{id: int, name: string, slug: string, brand_primary_color: string}
     */
    private function tenantPayload(Tenant $tenant, array $tenantColors): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'brand_primary_color' => $tenantColors[$tenant->id] ?? '#1F2937',
        ];
    }

    private function normalizePrimaryColor(?string $color): string
    {
        $normalized = strtoupper(trim((string) $color));

        if (preg_match('/^#[A-F0-9]{6}$/', $normalized) !== 1) {
            return '#1F2937';
        }

        return $normalized;
    }
}
