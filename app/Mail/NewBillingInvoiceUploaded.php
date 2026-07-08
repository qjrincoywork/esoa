<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Soa;

class NewBillingInvoiceUploaded extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * soa details
     *
     * @var $soa
     */
    public $soa;

    /**
     * Create the mailable announcing the newly uploaded billing invoice (SOA).
     */
    public function __construct(Soa $soa)
    {
        $this->soa = $soa;
    }

    /**
     * Build the envelope with a translated subject (labels.new_billing_invoice_uploaded.subject)
     * that embeds the SOA's soa_number.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('labels.new_billing_invoice_uploaded.subject', [
                'soanum' => $this->soa->soa_number
            ])
        );
    }

    /**
     * Render the email using the emails.esoa.new-bi-uploaded view.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.new-bi-uploaded',
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
