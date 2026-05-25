<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->date('deposit_date');
            $table->integer('mode_of_payment');
            $table->string('image');
            $table->string('excel');
            $table->string('pdf')->nullable();
            $table->longText('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('soa_account_payments', function (Blueprint $table) {
            $table->dropForeign(['account_payment_id']);
        });
        Schema::dropIfExists('account_payments');
    }
};
