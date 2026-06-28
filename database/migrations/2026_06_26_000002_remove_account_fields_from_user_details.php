<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop each column separately for SQL Server compatibility
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropColumn('account_type');
        });
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropColumn('account_code');
        });
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropColumn('branch_code');
        });
    }

    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->string('account_type')->nullable()->after('user_id');
            $table->longText('account_code')->nullable()->after('account_type');
            $table->longText('branch_code')->nullable()->after('account_code');
        });
    }
};
