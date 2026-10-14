<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The email notifications each user is excluded from ({@see \App\Enums\EmailNotification}).
     *
     * One row per user and notification, and only for exclusions: a user with no rows
     * receives everything, so every existing user keeps receiving what they did before,
     * and a notification added to the enum later reaches everyone with no backfill.
     */
    public function up(): void
    {
        Schema::create('user_notification_exclusions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('notification', 50);
            $table->timestamps();

            $table->unique(['user_id', 'notification']);
            $table->index('notification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_exclusions');
    }
};
