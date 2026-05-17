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
        Schema::create('soa_concerns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('soa_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('concern_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unique([
                'soa_id',
                'concern_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('soa_concerns');
    }
};
