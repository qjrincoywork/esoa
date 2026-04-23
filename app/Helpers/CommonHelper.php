<?php

namespace App\Helpers;

use App\Enums\Server;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Helpers\SqlDatabase;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class CommonHelper
{
    /**
     * Best-effort fix for legacy mojibake like:
     * - "PIÃ‘AS" or "PIÃƒâ€˜AS" -> "PIÑAS"
     *
     * NOTE: This is a heuristic. The real fix is to store text as proper UTF-8.
     */
    public static function convertStringEncoding($string)
    {
        if ($string === null) {
            return null;
        }

        // Case 1: String is valid UTF-8 but clearly mojibake (Ã… sequences, etc.)
        if (mb_check_encoding($string, 'UTF-8') && str_contains($string, 'Ã')) {
            // Common repair: interpret current UTF-8 string as ISO-8859-1 and
            // re-encode to UTF-8 once. This often turns "PIÃ‘AS" into "PIÑAS".
            $fixed = utf8_encode(utf8_decode($string));

            // Fix very common ñ/Ñ mojibake variants explicitly
            $fixed = strtr($fixed, [
                'Ã‘' => 'Ñ',
                'Ã±' => 'ñ',
                'Ã?' => 'Ñ',
            ]);

            return $fixed;
        }

        // Case 2: Not valid UTF-8 at all; try Latin1 -> UTF-8 conversion once.
        if (!mb_check_encoding($string, 'UTF-8')) {
            $fixed = @mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');

            if (mb_check_encoding($fixed, 'UTF-8')) {
                $fixed = strtr($fixed, [
                    'Ã‘' => 'Ñ',
                    'Ã±' => 'ñ',
                    'Ã?' => 'Ñ',
                ]);

                return $fixed;
            }

            return $string;
        }

        // Already valid and not obviously mojibake; return as-is.
        return $string;
    }

    /**
     * Format a date string according to the given parameters.
     *
     * @param  string|null  $date
     * @param  bool  $withTime
     * @return  string|null
     */
    public static function formatDate($date, $withTime = false)
    {
        if (!$date) {
            return null;
        }

        $date = Carbon::parse($date);

        return $withTime
            ? $date->format('F j, Y h:i A')
            : $date->format('F j, Y');
    }

    /**
     * Get filtered original values, excluding ignored keys.
     *
     * @param  object  $model
     * @return array
     */
    public static function getFilteredOriginal($model): array
    {
        return collect($model->getOriginal())
            ->filter(function ($value, $key) {
                return !in_array($key, config('vc.ignored_diff_keys', []));
            })
            ->toArray();
    }

    /**
     * Get filtered changed values, excluding ignored keys.
     *
     * @param  object  $model
     * @return array
     */
    public static function getFilteredChanges($model): array
    {
        return collect($model->getChanges())
            ->filter(function ($value, $key) {
                return !in_array($key, config('vc.ignored_diff_keys', []));
            })
            ->toArray();
    }

    /**
     * Check if a model has account code and request has file uploaded.
     *
     * @param  object  $model
     * @param  object  $request
     * @param  string  $fileField
     * @return bool
     */
    public static function hasFileAttachmentAndAccount($model, $request, string $fileField = 'file_pdf'): bool
    {
        return $request->hasFile($fileField) && !empty($model->account_code);
    }

    /**
     * Store uploaded files with SOA number in filename.
     *
     * @param  string  $soaNumber
     * @param  string  $accountCode
     * @param  string|null  $branchCode
     * @param  object  $request
     * @param  array  $validated
     * @param  array  $fileTypes
     * @return void
     */
    public static function storeUploadedFiles(
        string $soaNumber,
        string $accountCode,
        ?string $branchCode,
        $request,
        array &$validated,
        array $fileTypes = ['file_pdf', 'file_xls']
    ): void {
        $directory = $accountCode . (!empty($branchCode) ? "/" . $branchCode : "");

        foreach ($fileTypes as $fileType) {
            if ($request->hasFile($fileType)) {
                $file = $request->file($fileType);
                $filename = $soaNumber . '_' . now()->format('Ymd_His') . '.' . $file->getClientOriginalExtension();
                $validated[$fileType] = $file->storeAs($directory, $filename, env('BILLING_DISK', 'public'));
            }
        }
    }

    /**
     * Set client name from account or branch data.
     *
     * @param  object  $model
     * @return void
     */
    public static function setClientName($model): void
    {
        $account = (new SqlDatabase(Server::HMS))->getAccount($model->account_code);
        $model->client_name = $account->ac_name ?? $model->account_code;

        if (!empty($model->branch_code)) {
            $branch = (new SqlDatabase(Server::HMS))->getBranch($model->branch_code);
            $model->client_name = $branch?->br_branch_name ?? $model->branch_code;
        }
    }

    /**
     * Send billing invoice email and record activity.
     *
     * @param  object  $model
     * @param  object  $user
     * @param  string  $mailClass
     * @return void
     */
    public static function sendBillingInvoiceEmail($model, $user, string $mailClass): void
    {
        self::setClientName($model);
        $model->contact = config('vc.contact_email');

        $notifyEmail = config('vc.billing_notification_email', 'billing@example.com');
        Mail::to($notifyEmail)->send(new $mailClass($model));

        $model->recordActivity('billing_invoice_email_sent', [
            'to' => [
                'soa_number' => $model->soa_number,
                'file_pdf' => $model->file_pdf,
                'notified_email' => $notifyEmail,
            ],
        ], $user);
    }

    /**
     * Validate if a resource is in paid status.
     *
     * @param  object  $model
     * @param  mixed  $paidStatus
     * @throws \Exception
     * @return void
     */
    public static function validateNotPaid($model, $paidStatus): void
    {
        if ($model->status === $paidStatus) {
            throw new \Exception('Record has already been paid.');
        }
    }

    public static function assertUserMayAccessModel($model): void
    {
        $authUser = auth()->user();
        if (!$authUser) {
            abort(Response::HTTP_UNAUTHORIZED);
        }
        if ($model->user_id !== $authUser->id) {
            abort(Response::HTTP_FORBIDDEN);
        }
        if (
            $authUser->hasRole('superadmin')
            || $authUser->hasRole('billing_admin')
            || ($model->user_id === $authUser->id)
        ) {
            return;
        }
    }
}
