<?php

use App\Enums\RemittanceAdviceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payments', function (Blueprint $table) {
            $table->integer('status')->default(RemittanceAdviceStatus::SUBMITTED)->after('user_id');
            $table->decimal('amount', 15, 2)->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('account_payments', function (Blueprint $table) {
            $table->dropColumn(['status', 'amount']);
        });
    }
};
