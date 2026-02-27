<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class DealPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function view(User $user, Deal $deal): bool
    {
        return $this->canAccessTenantRecord($user, (int) $deal->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function update(User $user, Deal $deal): bool
    {
        return $this->canAccessTenantRecord($user, (int) $deal->tenant_id);
    }

    public function delete(User $user, Deal $deal): bool
    {
        return $this->canAccessTenantRecord($user, (int) $deal->tenant_id);
    }
}
