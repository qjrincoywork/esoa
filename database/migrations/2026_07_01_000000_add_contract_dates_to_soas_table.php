<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soas', function (Blueprint $table) {
            $table->date('contract_date_from')->nullable()->after('period_date_to');
            $table->date('contract_date_to')->nullable()->after('contract_date_from');
        });
    }

    public function down(): void
    {
        Schema::table('soas', function (Blueprint $table) {
            $table->dropColumn(['contract_date_from', 'contract_date_to']);
        });
    }
};
