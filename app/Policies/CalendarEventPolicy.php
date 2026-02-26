<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class CalendarEventPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->canAccessTenantRecord($user, (int) $calendarEvent->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->canAccessActiveTenant($user);
    }

    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->canAccessTenantRecord($user, (int) $calendarEvent->tenant_id);
    }

    public function delete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->canAccessTenantRecord($user, (int) $calendarEvent->tenant_id);
    }
}
