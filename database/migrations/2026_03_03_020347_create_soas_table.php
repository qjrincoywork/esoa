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
        Schema::create('soas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->string('soa_number');
            $table->string('account_type');
            $table->string('account_code');
            $table->string('branch_code')->nullable();
            $table->json('billing_ref')->nullable();
            $table->integer('billing_ref_from')->nullable();
            $table->integer('bill_type');
            $table->integer('status');
            $table->date('due_date');
            $table->date('period_date_from');
            $table->date('period_date_to');
            $table->decimal('amount', 15, 2);
            $table->longText('file_pdf')->nullable();
            $table->longText('file_xls')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('soas');
    }
};
