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
            Log::info('End Logging');
        } catch (\Exception $e) {
            Log::error('Job failed: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            throw $e;
        }
    }
}
