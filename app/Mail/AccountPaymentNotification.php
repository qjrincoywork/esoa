<?php

namespace App\Mail;

use App\Helpers\CommonHelper;
use App\Models\AccountPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountPaymentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public AccountPayment $accountPayment;

    /**
     * Create the mailable for the given account payment record.
     */
    public function __construct(AccountPayment $accountPayment)
    {
        $this->accountPayment = $accountPayment;
    }

    /**
     * Build the envelope with subject "Account payment notification for {deposit date}"
     * (falling back to "record" when the deposit date is empty).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account payment notification for ' . (CommonHelper::formatDate($this->accountPayment->deposit_date) ?? 'record')
        );
    }

    /**
     * Render the email using the emails.esoa.account-payment-notification view.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.account-payment-notification',
        );
    }

    /**
     * No attachments are sent with this notification.
     */
    public function attachments(): array
    {
        return [];
    }
}
