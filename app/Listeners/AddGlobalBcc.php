<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Blind-copy every outgoing email to the addresses in `mail.bcc`, so delivery can be
 * checked and validated without touching each Mailable or call site.
 *
 * Hooked on MessageSending, which fires for mailables, notifications and raw mail alike,
 * and at the moment of sending — so queued mail picks up the list the worker sees.
 * An address already on the message (to, cc or bcc) is skipped to avoid duplicates.
 */
class AddGlobalBcc
{
    public function handle(MessageSending $event): void
    {
        $bcc = config('mail.bcc', []);

        if (empty($bcc)) {
            return;
        }

        $message = $event->message;

        $existing = array_map(
            fn (Address $address) => strtolower($address->getAddress()),
            [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()],
        );

        $missing = array_filter($bcc, fn (string $address) => !in_array(strtolower($address), $existing, true));

        if (!empty($missing)) {
            $message->addBcc(...$missing);
        }
    }
}
