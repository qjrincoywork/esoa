<?php

namespace App\Jobs;


use App\Models\Account;
use App\Models\Contact;
// use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\{ DB, Log };
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportAccountsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chunk;

    /**
     * Create a new job instance.
     */
    public function __construct($chunk)
    {
        $this->chunk = $chunk;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $authId = 1; // fallback if queue doesn't have auth

        try {
            $authId = 1; // fallback if queue doesn't have auth
            Log::info('Start Accounts Logging');
            foreach ($this->chunk as $account) {
                // contact
                $contactId = null;
                if (
                    !empty($account->ac_conper)
                    || !empty($account->ac_phone)
                ) {
                    $contact = Contact::create([
                        'created_by' => $authId,
                        'name' => $account->ac_conper,
                        'number' => $account->ac_phone,
                    ]);
                    $contactId = $contact->id;
                }

                // account
                Account::create([
                    'contact_id' => $contactId,
                    'updated_by' => $authId,
                    'agent_id' => $account->agent_id,
                    'code' => $account->ac_code,
                    'name' => $account->ac_name,
                    'sob' => $account->ac_sob,
                    'remarks' => $account->ac_note,
                    'address' => $account->ac_address,
                    'logo' => $account->ac_logo,
                    'effectivity_date' => $account->ac_effdate,
                    'renewal_date' => $account->ac_rendate,
                    'expiry_date' => $account->ac_expiry,
                    'cancel_date' => $account->ac_candate,
                    'cancel_reason' => $account->ac_cancel_reason,
                    'type' => $account->ac_contype,
                    'payment_type' => $account->ac_paytype,
                    'contribution_type' => $account->ac_insurance,
                    'pre_existing_coverage' => $account->ac_mpec,
                    'billing_cutoff_date' => $account->ac_billcutoff,
                    'extension_days' => $account->ac_extension,
                    'additional_extension_days' => $account->ac_addextension,
                    'reimbursement_no_days' => $account->ac_reimbursement_no_day,
                    'dental_rate' => $account->ac_dental,
                    'tin' => $account->ac_tin,
                    'production_credit' => $account->ac_prodcred,
                    'vat_classification' => $account->ac_vatclass,
                    'account_type' => $account->ac_accttype,
                    'type_of_foreclaims' => $account->ac_type_foreclaims,
                    'integration' => $account->ac_integration,
                    'is_vchealth_activated' => $account->ac_isvchealth_activated,
                    'is_ar_integration' => $account->ch_ar_integration,
                    'is_showvirtual' => $account->ac_showvirtual,
                    'commission_type' => $account->ac_commision_type,
                ]);
            }

            Log::info('End Accounts Logging');
        } catch (\Exception $e) {
            Log::error('Job failed: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            throw $e;
        }
    }
}
