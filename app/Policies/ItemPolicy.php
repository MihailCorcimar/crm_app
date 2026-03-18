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
        return $this->canAccessActiveTenant($user, 'products', 'read');
    }

    public function view(User $user, Item $item): bool
    {
        return $this->canAccessTenantRecord($user, (int) $item->tenant_id, 'products', 'read');
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveTenant($user, 'products', 'create');
    }

    public function update(User $user, Item $item): bool
    {
        return $this->canAccessTenantRecord($user, (int) $item->tenant_id, 'products', 'update');
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->canAccessTenantRecord($user, (int) $item->tenant_id, 'products', 'delete');
    }
}
