<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantContext
{
    public static function id(?Request $request = null): ?int
    {
        $request ??= app()->bound('request') ? request() : null;

        $attributeTenantId = data_get($request?->attributes->get('tenantContext'), 'active.id');
        if (is_numeric($attributeTenantId)) {
            return (int) $attributeTenantId;
        }

        $sessionTenantId = $request?->hasSession() ? $request->session()->get('current_tenant_id') : null;
        if (is_numeric($sessionTenantId)) {
            return (int) $sessionTenantId;
        }

        /** @var User|null $user */
        $user = $request?->user() ?? Auth::user();
        $userTenantId = $user?->current_tenant_id;
        if (is_numeric($userTenantId)) {
            return (int) $userTenantId;
        }

        return null;
    }

    public static function slug(?Request $request = null): ?string
    {
        $request ??= app()->bound('request') ? request() : null;

        $attributeTenantSlug = data_get($request?->attributes->get('tenantContext'), 'active.slug');
        if (is_string($attributeTenantSlug) && $attributeTenantSlug !== '') {
            return $attributeTenantSlug;
        }

        /** @var User|null $user */
        $user = $request?->user() ?? Auth::user();
        if ($user !== null && is_numeric($user->current_tenant_id)) {
            return $user->tenants()
                ->where('tenants.id', (int) $user->current_tenant_id)
                ->value('slug');
        }

        return null;
    }
}
