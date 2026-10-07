<?php

namespace App\Support;

use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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
 *
 * Session events are written once per action, not once per request. Laravel fires its
 * login event for every request that restores a user from the "remember me" cookie, and
 * a page that lapsed fires several requests at once (the page, its deferred props, a
 * second tab) — each of which would otherwise record the same return. So the first
 * request of a burst claims the entry and the rest are dropped ({@see claim()}).
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
        AuditEvent::SESSION_RESUMED => 'resumed a remembered session',
        AuditEvent::LOGGED_OUT => 'signed out',
        AuditEvent::PASSWORD_CHANGED => 'changed their password',
    ];

    /**
     * The events that describe the state of a session. Each is recorded at most once per
     * user and browser within {@see REPEAT_WINDOW_SECONDS}, and recording one releases the
     * others — so signing out and straight back in still records both.
     */
    private const SESSION_EVENTS = [
        AuditEvent::LOGGED_IN,
        AuditEvent::SESSION_RESUMED,
        AuditEvent::LOGGED_OUT,
    ];

    /** How long a burst of identical session events is treated as one. */
    private const REPEAT_WINDOW_SECONDS = 60;

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
        $context = app()->runningInConsole() ? [] : AuditContext::forRequest(request());

        if (!self::claim($user, $event, $context)) {
            return;
        }

        try {
            activity(AuditLogName::AUTHENTICATION)
                ->causedBy($user)
                ->performedOn($user)
                ->event($event)
                ->withProperties(array_filter(['context' => $context]))
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

    /**
     * Claim the right to record a session event; false when this burst already has.
     *
     * The claim is a cache key per event, user and browser, taken with `add()` — which
     * only writes when the key is absent, atomically on the database store (an insert
     * against its primary key) — so of several requests racing to record the same
     * return, exactly one wins. A successful claim releases the user's other session
     * events, since the session has moved on from them.
     *
     * Events outside the session (a password change) are always recorded, and a cache
     * that cannot answer lets the entry through: a duplicate is better than a gap.
     *
     * @param  array<string, string>  $context
     */
    private static function claim(User $user, string $event, array $context): bool
    {
        if (!in_array($event, self::SESSION_EVENTS, true)) {
            return true;
        }

        try {
            if (!Cache::add(self::claimKey($user, $event, $context), true, self::REPEAT_WINDOW_SECONDS)) {
                return false;
            }

            foreach (array_diff(self::SESSION_EVENTS, [$event]) as $other) {
                Cache::forget(self::claimKey($user, $other, $context));
            }
        } catch (\Throwable $e) {
            Log::warning('AuthenticationAudit: repeat check unavailable, recording anyway', [
                'event' => $event,
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * One key per event, user and browser: the same person signing in from two devices
     * at once is two entries, not a repeat.
     *
     * @param  array<string, string>  $context
     */
    private static function claimKey(User $user, string $event, array $context): string
    {
        return sprintf(
            'audit:auth:%s:%s:%s',
            $event,
            $user->getKey(),
            sha1(($context['ip'] ?? '') . '|' . ($context['user_agent'] ?? ''))
        );
    }
}
