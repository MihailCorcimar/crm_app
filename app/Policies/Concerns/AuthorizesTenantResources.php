<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesTenantResources
{
    protected function canAccessActiveTenant(User $user, ?string $module = null, string $action = 'read'): bool
    {
        if (! is_numeric($user->current_tenant_id)) {
            return false;
        }

        $isTenantMember = $user->tenants()
            ->where('tenants.id', (int) $user->current_tenant_id)
            ->exists();

        if (! $isTenantMember) {
            return false;
        }

        if ($module === null) {
            return true;
        }

        return $user->hasModulePermission($module, $action);
    }

    protected function canAccessTenantRecord(User $user, int $tenantId, ?string $module = null, string $action = 'read'): bool
    {
        if (! $this->canAccessActiveTenant($user, $module, $action)) {
            return false;
        }

        return (int) $user->current_tenant_id === $tenantId;
    }
}
