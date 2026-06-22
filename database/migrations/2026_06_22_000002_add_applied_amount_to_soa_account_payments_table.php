<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soa_account_payments', function (Blueprint $table) {
            $table->decimal('applied_amount', 15, 2)->default(0)->after('account_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('soa_account_payments', function (Blueprint $table) {
            $table->dropColumn('applied_amount');
        });
    }
};
