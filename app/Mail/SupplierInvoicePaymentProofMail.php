<?php

namespace App\Mail;

use App\Models\SupplierInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierInvoicePaymentProofMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupplierInvoice $invoice)
    {
        $this->invoice->loadMissing('supplier:id,name,email');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Comprovativo de Pagamento - Fatura "%s"', (string) $this->invoice->number),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.supplier_invoice_payment_proof',
            with: [
                'invoice' => $this->invoice,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->invoice->payment_proof_path) {
            return [];
        }

        $extension = pathinfo($this->invoice->payment_proof_path, PATHINFO_EXTENSION);
        $filename = sprintf(
            'comprovativo-fatura-%s%s',
            (string) $this->invoice->number,
            $extension ? '.'.$extension : ''
        );

        return [
            Attachment::fromStorageDisk('local', $this->invoice->payment_proof_path)
                ->as($filename),
        ];
    }
}

