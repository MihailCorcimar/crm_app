<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DealProposalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $bodyText,
        public readonly string $proposalPath,
        public readonly string $proposalName,
        public readonly ?string $proposalMimeType = null,
    ) {}

    public function build(): self
    {
        $mailable = $this
            ->subject($this->subjectLine)
            ->view('emails.deals.proposal')
            ->with([
                'bodyText' => $this->bodyText,
            ]);

        $content = Storage::disk('local')->get($this->proposalPath);

        return $mailable->attachData($content, $this->proposalName, [
            'mime' => $this->proposalMimeType ?: 'application/octet-stream',
        ]);
    }
}
