<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Soa;
use App\Enums\SoaAging;
use App\Enums\SoaStatus;
use Illuminate\Support\Collection;

class BillingInvoiceDueReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * SOAs for this user
     *
     * @var Collection<int, Soa>
     */
    public Collection $soas;

    /**
     * Aging classification label
     *
     * @var string
     */
    public string $agingLabel;

    /**
     * Number of SOAs in this batch
     *
     * @var int
     */
    public int $soaCount;

    /**
     * Create a new message instance.
     *
     * @param Collection<int, Soa> $soas
     * @param string $agingLabel
     */
    public function __construct(Collection $soas, string $agingLabel)
    {
        $this->soas = $soas;
        $this->agingLabel = $agingLabel;
        $this->soaCount = $soas->count();
    }

    /**
     * Get the message envelope.
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
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.billing-invoice-due-reminder',
            with: [
                'soas' => $this->soas,
                'agingLabel' => $this->agingLabel,
                'soaCount' => $this->soaCount,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
