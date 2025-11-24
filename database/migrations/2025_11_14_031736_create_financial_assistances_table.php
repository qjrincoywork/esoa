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
        Schema::create('financial_assistances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('fin_code')
                ->comment('NONE - FA000; SUNLIFE - FA001; PRULIFE - FA002;');
            $table->float('amount')->nullable();
            $table->float('natural_death_amount')->nullable();
            $table->float('accident_death_amount')->nullable();
            $table->float('dismemberment_amount')->nullable();
            $table->longText('remarks');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_assistances');
    }
};
