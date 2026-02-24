<?php

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'tenant.active'])->get('/tenant/context', function (Request $request) {
    $tenantContext = $request->attributes->get('tenantContext');
    $activeTenant = data_get($tenantContext, 'active');

    if ($activeTenant === null) {
        $tenantId = TenantContext::id($request);

        if ($tenantId !== null) {
            $activeTenant = Tenant::query()
                ->select(['id', 'name', 'slug'])
                ->where('id', $tenantId)
                ->first()
                ?->only(['id', 'name', 'slug']);
        }
    }

    return response()->json([
        'tenant' => $activeTenant,
    ]);
});
