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
        Schema::create('user_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('suffix_id');
            $table->bigInteger('gender_id');
            $table->bigInteger('civil_status_id');
            $table->bigInteger('citizenship_id');
            $table->bigInteger('department_id');
            $table->bigInteger('position_id');
            $table->string('first_name')->collation('SQL_Latin1_General_CP1_CS_AS');
            $table->string('middle_name')->nullable()->collation('SQL_Latin1_General_CP1_CS_AS');
            $table->string('last_name')->collation('SQL_Latin1_General_CP1_CS_AS');
            $table->string('birthdate')->nullable();
            $table->string('employee_no')->nullable()->collation('SQL_Latin1_General_CP1_CS_AS');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_details');
    }
};
