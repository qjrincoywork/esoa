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
        Schema::create('main_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('contact_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->string('sob')
                ->nullable()
                ->comment('Summary Of Benefits');
            $table->longText('remarks')->nullable();
            $table->longText('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('main_accounts');
    }
};
