<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Advisories shown to every user right after they sign in (service advisories,
     * maintenance notices). An advisory is shown while it is enabled and inside its
     * window; an open-ended bound — no start or no end — means "from now" / "until
     * disabled", so the switch alone is enough for a notice without a fixed schedule.
     */
    public function up(): void
    {
        Schema::create('advisories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('image')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The sign-in lookup filters on exactly these columns.
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisories');
    }
};
