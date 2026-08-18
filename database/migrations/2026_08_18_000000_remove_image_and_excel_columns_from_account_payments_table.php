<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop each column separately for SQL Server compatibility
        Schema::table('account_payments', function (Blueprint $table) {
            $table->dropColumn('image');
        });
        Schema::table('account_payments', function (Blueprint $table) {
            $table->dropColumn('excel');
        });
    }

    public function down(): void
    {
        Schema::table('account_payments', function (Blueprint $table) {
            $table->string('image')->after('mode_of_payment');
            $table->string('excel')->after('image');
        });
    }
};
