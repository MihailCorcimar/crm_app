<?php

namespace App\Policies;

use App\Models\DealAutomationRule;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class DealAutomationRulePolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveTenant($user, 'automations', 'read');
    }

    public function view(User $user, DealAutomationRule $rule): bool
    {
        return $this->canAccessTenantRecord($user, (int) $rule->tenant_id, 'automations', 'read');
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveTenant($user, 'automations', 'create');
    }

    public function update(User $user, DealAutomationRule $rule): bool
    {
        return $this->canAccessTenantRecord($user, (int) $rule->tenant_id, 'automations', 'update');
    }

    public function delete(User $user, DealAutomationRule $rule): bool
    {
        return $this->canAccessTenantRecord($user, (int) $rule->tenant_id, 'automations', 'delete');
    }
}
