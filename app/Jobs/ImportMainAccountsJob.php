<?php

namespace App\Jobs;


use App\Models\Contact;
use App\Models\MainAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\{ DB, Log };
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class ImportMainAccountsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chunk;

    /**
     * Create the job with the chunk of legacy main-account rows to import.
     *
     * @param  iterable  $chunk  Legacy main-account records (objects exposing ma_* fields).
     */
    public function __construct($chunk)
    {
        $this->chunk = $chunk;
    }

    /**
     * Import the chunked legacy main accounts into the main_accounts table.
     *
     * For each row a Contact is created when both contact person and number
     * are present, and a MainAccount is created only when both code and name
     * are present (linked to the contact when one was made). The chunk runs in
     * one transaction that rolls back and rethrows on any failure.
     */
    public function handle(): void
    {
        DB::beginTransaction();
        try {
            $authId = 1; // fallback if queue doesn't have auth
            Log::info('Start Logging');

            foreach ($this->chunk as $mainAccount) {
                // contact
                $contactId = null;
                if (
                    !empty($mainAccount->ma_contactperson)
                    && !empty($mainAccount->ma_contactno)
                ) {
                    $contact = Contact::create([
                        'created_by' => $authId,
                        'name' => $mainAccount->ma_contactperson,
                        'number' => $mainAccount->ma_contactno,
                    ]);
                    $contactId = $contact->id;
                }

                if (
                    !empty($mainAccount->ma_code)
                    && !empty($mainAccount->ma_name)
                ) {
                    // account
                    MainAccount::create([
                        'code' => $mainAccount->ma_code,
                        'name' => $mainAccount->ma_name,
                        'sob' => $mainAccount->ma_sob,
                        'remarks' => $mainAccount->ma_rem,
                        'address' => $mainAccount->ma_address,
                        'contact_id' => $contactId,
                    ]);
                }
            }
            DB::commit();
            Log::info('End Logging');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Job failed: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            throw $e;
        }
    }
}
