<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesTenantResources
{
    protected function canAccessActiveTenant(User $user): bool
    {
        if (! is_numeric($user->current_tenant_id)) {
            return false;
        }

        return $user->tenants()
            ->where('tenants.id', (int) $user->current_tenant_id)
            ->exists();
    }

    protected function canAccessTenantRecord(User $user, int $tenantId): bool
    {
        if (! $this->canAccessActiveTenant($user)) {
            return false;
        }

        return (int) $user->current_tenant_id === $tenantId;
    }
}
