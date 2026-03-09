<?php

namespace App\Observers;

use App\Jobs\RefreshAiSuggestionsJob;
use App\Models\CalendarEvent;

class CalendarEventObserver
{
    public function created(CalendarEvent $event): void
    {
        $this->dispatchRefresh($event);
    }

    public function updated(CalendarEvent $event): void
    {
        $this->dispatchRefresh($event);
    }

    private function dispatchRefresh(CalendarEvent $event): void
    {
        if (! is_numeric($event->tenant_id)) {
            return;
        }

        $userId = is_numeric($event->owner_id) ? (int) $event->owner_id : null;

        RefreshAiSuggestionsJob::dispatch((int) $event->tenant_id, $userId)->afterResponse();
    }
}
