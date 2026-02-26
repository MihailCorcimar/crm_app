<?php

namespace App\Policies;

use App\Models\Entity;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class EntityPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function view(User $user, Entity $entity): bool
    {
        return $this->canAccessTenantRecord($user, (int) $entity->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function update(User $user, Entity $entity): bool
    {
        return $this->canAccessTenantRecord($user, (int) $entity->tenant_id);
    }

    public function delete(User $user, Entity $entity): bool
    {
        return $this->canAccessTenantRecord($user, (int) $entity->tenant_id);
    }
}
