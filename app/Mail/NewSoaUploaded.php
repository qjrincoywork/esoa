<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewSoaUploaded extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * soa details
     *
     * @var $soa
     */
    public $soa;

    /**
     * Create the mailable for a newly uploaded legacy SOA record.
     *
     * @param  $soa  A legacy upload row exposing the up_soanum field.
     */
    public function __construct($soa)
    {
        $this->soa = $soa;
    }

    /**
     * Build the envelope with a translated subject (labels.new_soa_uploaded_subject)
     * that embeds the upload's up_soanum.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('labels.new_soa_uploaded_subject', [
                'soanum' => $this->soa->up_soanum
            ])
        );
    }

    /**
     * Render the email using the emails.esoa.new-soa-uploaded view.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.new-soa-uploaded',
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
