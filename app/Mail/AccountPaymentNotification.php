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

    public function __construct(AccountPayment $accountPayment)
    {
        $this->accountPayment = $accountPayment;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account payment notification for ' . (CommonHelper::formatDate($this->accountPayment->deposit_date) ?? 'record')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.account-payment-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
