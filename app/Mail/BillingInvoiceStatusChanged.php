<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Soa;
use App\Enums\SoaStatus;

class BillingInvoiceStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * soa details
     *
     * @var Soa
     */
    public $soa;

    /**
     * Create the mailable for the SOA whose status changed.
     */
    public function __construct(Soa $soa)
    {
        $this->soa = $soa;
    }

    /**
     * Build the envelope with a translated subject (labels.billing_invoice_status_changed.subject)
     * embedding the SOA number and its new human-readable status label.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('labels.billing_invoice_status_changed.subject', [
                'soanum' => $this->soa->soa_number,
                'status_label' => SoaStatus::label((int) $this->soa->status),
            ])
        );
    }

    /**
     * Render the email using the emails.esoa.billing-invoice-status-changed view.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.billing-invoice-status-changed',
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
