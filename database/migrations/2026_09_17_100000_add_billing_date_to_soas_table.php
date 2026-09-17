<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give a billing invoice a bill date of its own.
     *
     * The list has always shown a "Bill Date" and filtered on it, but the value came
     * from `created_at` — the moment the row was uploaded, which is not the same thing
     * and cannot be corrected when an invoice is entered late. The column separates the
     * two: `created_at` stays the audit fact, `billing_date` becomes the billing one.
     *
     * Existing rows are seeded from `created_at`, so nothing on the list or in a saved
     * filter changes the day this runs; only the ability to correct it is new.
     */
    public function up(): void
    {
        Schema::table('soas', function (Blueprint $table) {
            $table->date('billing_date')->nullable()->after('due_date');
        });

        // Date part only: `created_at` is a timestamp and this column is not.
        DB::table('soas')
            ->whereNull('billing_date')
            ->whereNotNull('created_at')
            ->update(['billing_date' => DB::raw('CAST(created_at AS date)')]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('soas', function (Blueprint $table) {
            $table->dropColumn('billing_date');
        });
    }
};
