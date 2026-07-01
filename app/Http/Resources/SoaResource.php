<?php

namespace App\Http\Resources;

use App\Enums\BillType;
use App\Enums\Server;
use App\Enums\SoaStatus;
use App\Helpers\CommonHelper;
use App\Helpers\SqlDatabase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SoaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'soa_number' => $this->soa_number,
            'billing_ref' => $this->billing_ref,
            'billing_ref_names' => $this->getBillingRefNames($this->billing_ref),
            'bill_type' => BillType::label((int) $this->bill_type),
            'created_at' => CommonHelper::formatDate($this->created_at),
            'due_date' => CommonHelper::formatDate($this->due_date),
            'due_in' => $this->formatDaysDue($this->due_date),
            'period_date_from' => $this->period_date_from,
            'period_date_to' => $this->period_date_to,
            'period_coverage' => Str::upper(CommonHelper::formatDate($this->period_date_from) . ' TO ' . CommonHelper::formatDate($this->period_date_to)),
            'account_code' => $this->account_code,
            'branch_code' => $this->branch_code,
            'account_name' => CommonHelper::convertStringEncoding($this->getAccountName($this->account_code)),
            'branch_name' => CommonHelper::convertStringEncoding($this->getBranchName($this->branch_code)),
            'amount' => number_format($this->amount, 2),
            'amount_raw' => (float) $this->amount,
            'file_pdf' => $this->file_pdf,
            'file_xls' => $this->file_xls,
            'deleted_at' => $this->deleted_at,
            'status_color' => SoaStatus::color((int) $this->status),
            'status' => SoaStatus::label((int) $this->status),
            'soa_activities' => $this->whenLoaded('soaActivity', function () use ($request) {
                return SoaActivityListResource::collection($this->soaActivity)->resolve($request);
            }, []),
        ];
    }

    public function getBillingRefNames($billingRef) {
        $billingRefNames = [];
        if (!empty($billingRef)) {
            foreach ($billingRef as $ref) {
                $billingRefNames[] = $ref;
            }
        }

        return implode(', ', $billingRefNames);
    }

    /**
     * Format a due date into a human-readable string.
     *
     * @param string|null $date The date to format.
     *
     * @return string|null If the date is null, returns null.
     *   If the date is in the past, returns 'Past Due'.
     *   If the date is today, returns 'Due today'.
     *   If the date is in the future, returns 'Due in X days'.
     */
    public function formatDaysDue($date)
    {
        if (!$date) {
            return null;
        }

        $date = Carbon::parse($date);

        if ($date->isPast()) {
            return 'Past Due';
        }

        $days = (int) now()->diffInDays($date, true);

        return match ($days) {
            0 => 'Due Today',
            1 => 'Due Tomorrow',
            default => "Due in {$days} days",
        };
    }

    public function getAccountName($accountCode) {
        $account = (new SqlDatabase(Server::HMS))->getAccount($accountCode);

        return $account->ac_name;
    }

    public function getAccountExpiryDate($accountCode) {
        $account = (new SqlDatabase(Server::HMS))->getAccount($accountCode);

        return CommonHelper::formatDate($account->expiry_date);
    }

    public function getBranchName($branchCode) {
        $branch = (new SqlDatabase(Server::HMS))->getBranch($branchCode);

        return $branch->br_branch_name ?? '';
    }
}
