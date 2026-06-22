<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soa_official_receipts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('soa_id')->constrained('soas')->cascadeOnDelete();
            $table->foreignId('official_receipt_id')->constrained('official_receipts')->cascadeOnDelete();
            $table->unique(['soa_id', 'official_receipt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soa_official_receipts');
    }
};
