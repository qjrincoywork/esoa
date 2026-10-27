<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\LoginAdvisories;
use Illuminate\Auth\Events\Login;

/**
 * Queue the live advisories to pop up on the first page after signing in
 * ({@see LoginAdvisories}).
 *
 * Fires for every sign-in — the login form, the two-factor challenge, and a session
 * restored from the "remember me" cookie — since each is someone arriving at the app
 * who has not yet seen what is currently announced.
 */
class QueueLoginAdvisories
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            LoginAdvisories::queue(request());
        }
    }
}
