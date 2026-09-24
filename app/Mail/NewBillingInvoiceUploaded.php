<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Helpers\CommonHelper;
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
     *
     * The view greets the client by name and prints a contact number, neither of which
     * is a column on the SOA. Queued mail restores the model from its key alone, so
     * whatever the sender attached in memory is gone by the time a worker renders this;
     * the fields are therefore resolved here, where they are actually needed. Already
     * populated (the synchronous path), it costs nothing.
     */
    public function content(): Content
    {
        CommonHelper::prepareBillingInvoiceForEmail($this->soa);

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
