<?php

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Support\AuthenticationAudit;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;

/**
 * Put every sign-in and sign-out on the user's audit trail ({@see AuthenticationAudit}).
 *
 * Separate from {@see RecordUserLogin}, which stamps a column the credential reports
 * read: that one answers "has this user ever signed in", this one "when, and from where".
 */
class RecordAuthenticationActivity
{
    /**
     * Laravel fires the same Login event for two different things: credentials entered on
     * the sign-in form, and a lapsed session silently restored from the "remember me"
     * cookie on whatever page the user next opened. Only the first is a sign-in; the
     * second is recorded as a resumed session, so the trail does not claim a password was
     * typed on a `GET /` it never was.
     */
    public function handleLogin(Login $event): void
    {
        if (!$event->user instanceof User) {
            return;
        }

        AuthenticationAudit::record(
            $event->user,
            $this->restoredFromCookie($event->guard) ? AuditEvent::SESSION_RESUMED : AuditEvent::LOGGED_IN
        );
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            AuthenticationAudit::record($event->user, AuditEvent::LOGGED_OUT);
        }
    }

    /**
     * Whether the guard authenticated this request from its "remember me" cookie.
     *
     * The session guard sets this only on that path — ticking "remember me" on the form
     * does not — and guards without the notion simply never restore that way.
     */
    private function restoredFromCookie(string $guard): bool
    {
        $instance = Auth::guard($guard);

        return method_exists($instance, 'viaRemember') && $instance->viaRemember();
    }
}
