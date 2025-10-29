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
        Schema::create('suffixes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->collation('SQL_Latin1_General_CP1_CS_AS');
            $table->text('description')->nullable()->collation('SQL_Latin1_General_CP1_CS_AS');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suffixes');
    }
};
