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
        return $this->canAccessActiveTenant($user, 'deals', 'read');
    }

    public function view(User $user, Deal $deal): bool
    {
        return $this->canAccessTenantRecord($user, (int) $deal->tenant_id, 'deals', 'read');
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveTenant($user, 'deals', 'create');
    }

    public function update(User $user, Deal $deal): bool
    {
        return $this->canAccessTenantRecord($user, (int) $deal->tenant_id, 'deals', 'update');
    }

    public function delete(User $user, Deal $deal): bool
    {
        return $this->canAccessTenantRecord($user, (int) $deal->tenant_id, 'deals', 'delete');
    }
}
