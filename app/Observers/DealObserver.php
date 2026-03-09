<?php

namespace App\Observers;

use App\Jobs\RefreshAiSuggestionsJob;
use App\Models\Deal;

class DealObserver
{
    public function created(Deal $deal): void
    {
        $this->dispatchRefresh($deal);
    }

    public function updated(Deal $deal): void
    {
        $this->dispatchRefresh($deal);
    }

    private function dispatchRefresh(Deal $deal): void
    {
        if (! is_numeric($deal->tenant_id)) {
            return;
        }

        $userId = is_numeric($deal->owner_id) ? (int) $deal->owner_id : null;

        RefreshAiSuggestionsJob::dispatch((int) $deal->tenant_id, $userId)->afterResponse();
    }
}
