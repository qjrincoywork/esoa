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
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code');
            $table->string('name');
            $table->longText('remarks')->nullable();
            $table->longText('address')->nullable();
            $table->string('logo')->nullable();
            $table->integer('contact_id')->nullable();
            $table->date('effectivity_date');
            $table->date('renewal_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('cancel_date')->nullable();
            $table->date('billing_cutoff_date')->nullable();
            $table->longText('cancel_reason')->nullable();
            $table->string('type')
                ->comment('Special account, standard account - SA, STD');
            $table->string('payment_type')
                ->nullable()
                ->comment('Contributory (C) or Subsidized (S)');
            $table->string('contribution_type')
                ->comment('SSS (S) or GSIS (G)')
                ->nullable();
            $table->string('pre_existing_coverage')
                ->nullable()
                ->comment('mpec');
            $table->integer('extension_days')->nullable();
            $table->integer('additional_extension_days')->nullable();
            $table->integer('reimbursement_no_days')->nullable();
            $table->float('dental_rate')->nullable();
            $table->string('tin')->nullable();
            $table->string('production_credit')
                ->nullable()
                ->comment('Direct Sales (D), Sales (S), Marketing (M)');
            $table->string('vat_classification')
                ->nullable()
                ->comment('V, VE');
            $table->string('account_type')
                ->nullable()
                ->comment('H, TA');
            $table->string('type_of_foreclaims')
                ->nullable()
                ->comment('HMO, TPA');
            $table->string('sob')
                ->nullable()
                ->comment('Summary Of Benefits');
            $table->string('commission_type')
                ->nullable();
            $table->integer('updated_by')
                ->nullable()
                ->comment('reference by user_id - users');
            $table->integer('agent_id')
                ->nullable()
                ->comment('reference by agent_id - agents');
            $table->integer('financial_assistance_id')
                ->nullable()
                ->comment('reference by financial_assistance_id - financial_assistances table');
            $table->integer('main_account_id')
                ->nullable()
                ->comment('reference by main_account_id - main_accounts table');
            $table->tinyInteger('integration')->default(1)->nullable();
            $table->tinyInteger('is_vchealth_activated')->default(1)->nullable();
            $table->tinyInteger('is_ar_integration')->default(1)->nullable();
            $table->tinyInteger('is_showvirtual')->default(1)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
