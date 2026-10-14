<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Blind-copy every outgoing email to the addresses in `mail.bcc`, so delivery can be
 * checked and validated without touching each Mailable or call site.
 *
 * Hooked on MessageSending, which fires for mailables, notifications and raw mail alike,
 * at the moment of sending. An address already on the message (to, cc or bcc) is
 * skipped to avoid duplicates.
 *
 * For queued mail "the moment of sending" is inside whichever worker takes the job —
 * possibly another deployment's, running its own config and code, since the queue
 * lives in a shared database. Code that queues mail should therefore stamp the list on
 * at dispatch with {@see missingFrom()}; this listener then finds it already present.
 */
class AddGlobalBcc
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        $missing = self::missingFrom(array_map(
            fn (Address $address) => $address->getAddress(),
            [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()],
        ));

        if (!empty($missing)) {
            $message->addBcc(...$missing);
        }
    }

    /**
     * The configured BCC addresses not already among the given recipients, compared
     * case-insensitively.
     *
     * @param  array<int, string|null>  $recipients
     * @return list<string>
     */
    public static function missingFrom(array $recipients): array
    {
        $bcc = config('mail.bcc', []);

        if (empty($bcc)) {
            return [];
        }

        $existing = array_map(static fn ($address): string => strtolower(trim((string) $address)), $recipients);

        return array_values(array_filter(
            $bcc,
            static fn (string $address): bool => !in_array(strtolower($address), $existing, true)
        ));
    }
}
