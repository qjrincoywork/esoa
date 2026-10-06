<?php

namespace App\Support;

use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Writes what a person does with their own session and credentials to the trail.
 *
 * The audited modules only record changes to their records, so a user who signs in to
 * read and never edits leaves no trace there — and "when was this account used, and
 * from where" is as much a part of a user's history as what they changed. None of
 * these is a model event (a sign-in is deliberately not an edit of the user, see
 * {@see User::recordLogin()}), so they are written by hand, on their own channel,
 * shaped like every other entry: the user as both causer and subject, and the request
 * the action arrived on as context.
 */
final class AuthenticationAudit
{
    /**
     * How each event reads after the user's name, e.g. "jdoe signed in".
     *
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        AuditEvent::LOGGED_IN => 'signed in',
        AuditEvent::LOGGED_OUT => 'signed out',
        AuditEvent::PASSWORD_CHANGED => 'changed their password',
    ];

    /**
     * Record one authentication event for the given user.
     *
     * Best-effort: the trail must never be the reason someone cannot sign in or out,
     * so a failure to write it is logged and swallowed.
     *
     * @param  string  $event  One of the authentication {@see AuditEvent} values.
     */
    public static function record(User $user, string $event): void
    {
        try {
            activity(AuditLogName::AUTHENTICATION)
                ->causedBy($user)
                ->performedOn($user)
                ->event($event)
                ->withProperties(array_filter([
                    'context' => app()->runningInConsole() ? [] : AuditContext::forRequest(request()),
                ]))
                ->log(sprintf(
                    '%s %s',
                    $user->username ?: $user->email,
                    self::DESCRIPTIONS[$event] ?? strtolower(AuditEvent::label($event))
                ));
        } catch (\Throwable $e) {
            Log::error('AuthenticationAudit: audit entry could not be written', [
                'event' => $event,
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
