<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_receipts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');                     // billing dept user who issued the OR
            $table->bigInteger('account_payment_id')->nullable(); // linked RA (optional traceability)
            $table->string('or_number')->unique();             // official receipt number
            $table->date('or_date');                           // date on the OR
            $table->decimal('amount', 15, 2);                  // total amount covered by this OR
            $table->string('file')->nullable();                // PDF attachment of the OR
            $table->longText('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_receipts');
    }
};
