<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track the credential lifecycle the user list and its reports are built on.
     *
     * - credentials_sent_at: when login credentials were last emailed (on verification).
     *   Tells "never issued" apart from "issued and since changed" — a cleared
     *   temporary_password_expires_at alone means either.
     * - password_changed_at: when the user last replaced their password themselves.
     * - last_login_at: when the user last signed in, so "has accessed the credentials
     *   they were sent" is answerable.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('credentials_sent_at')->nullable()->after('temporary_password_expires_at');
            $table->timestamp('password_changed_at')->nullable()->after('credentials_sent_at');
            $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['credentials_sent_at', 'password_changed_at', 'last_login_at']);
        });
    }

    /**
     * Seed the new columns from what the data already says, so existing users are not
     * all reported as "never sent" and "never accessed" on day one.
     *
     * Credentials are emailed when a user is verified, so the verification time stands
     * in for when they were sent. There is no login history, but sessions are stored in
     * the database: a user's latest session activity is the best available evidence that
     * they signed in. password_changed_at is left empty — when that happened was never
     * recorded — and the credential status reads a cleared temporary password instead.
     */
    private function backfill(): void
    {
        DB::table('users')
            ->whereNull('credentials_sent_at')
            ->whereNotNull('email_verified_at')
            ->update(['credentials_sent_at' => DB::raw('email_verified_at')]);

        if (!Schema::hasTable('sessions')) {
            return;
        }

        DB::table('sessions')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(last_activity) AS last_activity')
            ->get()
            ->each(fn ($session) => DB::table('users')
                ->where('id', $session->user_id)
                ->update(['last_login_at' => Carbon::createFromTimestamp((int) $session->last_activity)]));
    }
};
