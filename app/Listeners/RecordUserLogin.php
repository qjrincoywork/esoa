<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Stamp a user's last sign-in, so the credential reports can tell who has used the
 * credentials they were sent ({@see User::hasAccessedCredentials()}).
 *
 * Fires for every sign-in — the login form and "remember me" alike — which is what
 * "has accessed their credentials" means.
 */
class RecordUserLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->recordLogin();
        }
    }
}
