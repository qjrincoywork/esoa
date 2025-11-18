<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class Account extends Model
{
    /** @use HasFactory<\Database\Factories\AccountFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'updated_by', //reference by user_id - users
        'agent_id', //reference by agent_id - agents
        'contact_id', //reference by contact_id - contacts
        'code',
        'name',
        'remarks',
        'address',
        'logo',
        'effectivity_date',
        'renewal_date',
        'expiry_date',
        'cancel_date',
        'type',
        'payment_type',
        'contribution_type',
        'pre_existing_coverage', //mpec - pre-existing coverage
        'billing_cutoff_date',
        'extension_days',
        'additional_extension_days',
        'reimbursement_no_days',
        'dental_rate',
        'tin',
        'production_credit',
        'vat_classification',
        'account_type',
        'type_of_foreclaims', //HMO, TPA
        'sob',
        'integration',
        'cancel_reason',
        'is_vchealth_activated',
        'is_ar_integration',
        'is_showvirtual',
        'commission_type',
    ];
}
