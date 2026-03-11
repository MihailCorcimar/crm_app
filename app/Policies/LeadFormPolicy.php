<?php

namespace App\Policies;

use App\Models\LeadForm;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class LeadFormPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function view(User $user, LeadForm $leadForm): bool
    {
        return $this->canAccessTenantRecord($user, (int) $leadForm->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function update(User $user, LeadForm $leadForm): bool
    {
        return $this->canAccessTenantRecord($user, (int) $leadForm->tenant_id);
    }

    public function delete(User $user, LeadForm $leadForm): bool
    {
        return $this->canAccessTenantRecord($user, (int) $leadForm->tenant_id);
    }
}

