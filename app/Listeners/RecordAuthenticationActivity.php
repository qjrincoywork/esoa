<?php

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Support\AuthenticationAudit;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Put every sign-in and sign-out on the user's audit trail ({@see AuthenticationAudit}).
 *
 * Separate from {@see RecordUserLogin}, which stamps a column the credential reports
 * read: that one answers "has this user ever signed in", this one "when, and from where".
 */
class RecordAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            AuthenticationAudit::record($event->user, AuditEvent::LOGGED_IN);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            AuthenticationAudit::record($event->user, AuditEvent::LOGGED_OUT);
        }
    }
}
