<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index `soas.soa_number`.
     *
     * Every upload asks whether an SOA number is already taken, and until now that
     * question had no index to answer it with — one full table scan per invoice. A
     * single upload absorbs that; a batch of thousands multiplies it by the row count,
     * on a table that only grows.
     *
     * The index is deliberately NOT unique. The column holds duplicates today, so a
     * unique index would fail to build, and silently relaxing that to a non-unique one
     * later would hide the fact that nothing enforces uniqueness at the database level
     * — the application checks it, which leaves a window between two concurrent
     * uploads. Deduplicating and then making this unique is a separate, deliberate
     * decision with data to clean up first.
     */
    public function up(): void
    {
        Schema::table('soas', function (Blueprint $table) {
            $table->index('soa_number', 'soas_soa_number_index');
        });
    }

    /**
     * Drop the index again.
     */
    public function down(): void
    {
        Schema::table('soas', function (Blueprint $table) {
            $table->dropIndex('soas_soa_number_index');
        });
    }
};
