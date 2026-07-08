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

    /**
     * Create the reminder for an aging bucket, resolving the human aging label
     * and the SOA-list URL for that bucket from the given aging value.
     *
     * @param  int  $agingValue  The SoaAging bucket value.
     * @param  int  $soaCount    Number of due SOAs in that bucket for the recipient.
     */
    public function __construct(
        public int $agingValue,
        int $soaCount,
    ) {
        $this->soaCount = $soaCount;
        $this->agingLabel = SoaAging::label($agingValue);
        $this->listUrl = SoaAging::listUrl($agingValue);
    }

    /**
     * Build the envelope with a translated subject that includes the SOA count
     * and aging label, falling back to an English "Billing Invoice Due
     * Reminder - {label} ({count} items)" string.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('labels.billing_invoice_due_reminder.subject', [
                'count' => $this->soaCount,
                'aging' => $this->agingLabel,
            ]) ?? "Billing Invoice Due Reminder - {$this->agingLabel} ({$this->soaCount} items)"
        );
    }

    /**
     * Render the emails.esoa.billing-invoice-due-reminder view, passing the
     * aging label, SOA count, and list URL for the bucket.
     */
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

    /**
     * No attachments are sent with this reminder.
     */
    public function attachments(): array
    {
        return [];
    }
}
