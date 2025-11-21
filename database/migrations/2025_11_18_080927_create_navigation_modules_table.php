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
        Schema::create('navigation_modules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('permission_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('icon');
            $table->string('url');
            $table->unsignedBigInteger('navigation_id');
            $table->bigInteger('ref_id')->nullable();
            $table->integer('order_number');
            $table->integer('status')->default(1);
            $table->integer('created_by');
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('set null');

            $table->foreign('navigation_id')
                ->references('id')
                ->on('navigations')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_modules');
    }
};
