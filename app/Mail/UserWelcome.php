<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserWelcome extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create the welcome mailable carrying the new user and their temporary credentials.
     *
     * @param  User    $user           The newly created account.
     * @param  string  $plainPassword  The one-time plaintext password shown to the user.
     * @param  string  $expiresAt      When that temporary password expires.
     */
    public function __construct(
        public readonly User $user,
        public readonly string $plainPassword,
        public readonly string $expiresAt,
    ) {}

    /**
     * Build the envelope with the fixed subject "Welcome to ESOA — Your Account Credentials".
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ESOA — Your Account Credentials',
        );
    }

    /**
     * Render the email using the emails.esoa.user-welcome view.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.esoa.user-welcome',
        );
    }

    /**
     * No attachments are sent with this welcome email.
     */
    public function attachments(): array
    {
        return [];
    }
}
