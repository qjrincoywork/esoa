<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillingInvoiceFinalReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * billing invoice details
     *
     * @var $billingInvoice
     */
    public $billingInvoice;

    /**
     * Create a new message instance.
     */
    public function __construct($billingInvoice)
    {
        $this->billingInvoice = $billingInvoice;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('labels.new_billing_invoice_uploaded.subject', [
                'soanum' => $this->billingInvoice->soa_number
            ])
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.billing-invoice-final-reminder',
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
