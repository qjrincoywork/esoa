<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('account_type', 50)->nullable();
            $table->string('account_code', 191);
            $table->string('branch_code', 191)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('account_code');
        });

        // Migrate existing ACCOUNT_BRANCH_ADMIN (type 2) data from user_details
        DB::table('user_details')
            ->where('type', 2)
            ->whereNotNull('account_code')
            ->get()
            ->each(function ($detail) {
                DB::table('user_accounts')->insert([
                    'user_id'      => $detail->user_id,
                    'account_type' => $detail->account_type,
                    'account_code' => $detail->account_code,
                    'branch_code'  => $detail->branch_code,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Restore first user_account back to user_details before dropping
        DB::table('user_accounts')
            ->get()
            ->groupBy('user_id')
            ->each(function ($accounts, $userId) {
                $first = $accounts->first();
                DB::table('user_details')
                    ->where('user_id', $userId)
                    ->update([
                        'account_type' => $first->account_type,
                        'account_code' => $first->account_code,
                        'branch_code'  => $first->branch_code,
                    ]);
            });

        Schema::dropIfExists('user_accounts');
    }
};
