<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class ContactPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->canAccessTenantRecord($user, (int) $contact->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->canAccessTenantRecord($user, (int) $contact->tenant_id);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->canAccessTenantRecord($user, (int) $contact->tenant_id);
    }
}
