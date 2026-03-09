<?php

namespace App\Observers;

use App\Jobs\RefreshAiSuggestionsJob;
use App\Models\Contact;

class ContactObserver
{
    public function created(Contact $contact): void
    {
        $this->dispatchRefresh($contact);
    }

    public function updated(Contact $contact): void
    {
        $this->dispatchRefresh($contact);
    }

    private function dispatchRefresh(Contact $contact): void
    {
        if (! is_numeric($contact->tenant_id)) {
            return;
        }

        RefreshAiSuggestionsJob::dispatch((int) $contact->tenant_id, null)->afterResponse();
    }
}
