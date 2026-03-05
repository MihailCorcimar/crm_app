<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class ItemPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function view(User $user, Item $item): bool
    {
        return $this->canAccessTenantRecord($user, (int) $item->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function update(User $user, Item $item): bool
    {
        return $this->canAccessTenantRecord($user, (int) $item->tenant_id);
    }
}

