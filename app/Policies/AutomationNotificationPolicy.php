<?php

namespace App\Policies;

use App\Models\AutomationNotification;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class AutomationNotificationPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveTenant($user, 'automations', 'read');
    }

    public function view(User $user, AutomationNotification $notification): bool
    {
        return $this->canAccessTenantRecord($user, (int) $notification->tenant_id, 'automations', 'read')
            && (int) $notification->user_id === (int) $user->id;
    }

    public function update(User $user, AutomationNotification $notification): bool
    {
        return $this->canAccessTenantRecord($user, (int) $notification->tenant_id, 'automations', 'update')
            && (int) $notification->user_id === (int) $user->id;
    }
}
