<?php

namespace App\Mail;

use App\Enums\SoaAging;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillingInvoiceDueReminder extends Mailable
{
    use Queueable, SerializesModels;

    public string $agingLabel;

    public int $soaCount;

    public string $listUrl;

    public function __construct(
        public int $agingValue,
        int $soaCount,
    ) {
        $this->soaCount = $soaCount;
        $this->agingLabel = SoaAging::label($agingValue);
        $this->listUrl = SoaAging::listUrl($agingValue);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('labels.billing_invoice_due_reminder.subject', [
                'count' => $this->soaCount,
                'aging' => $this->agingLabel,
            ]) ?? "Billing Invoice Due Reminder - {$this->agingLabel} ({$this->soaCount} items)"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.billing-invoice-due-reminder',
            with: [
                'agingLabel' => $this->agingLabel,
                'soaCount' => $this->soaCount,
                'listUrl' => $this->listUrl,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
