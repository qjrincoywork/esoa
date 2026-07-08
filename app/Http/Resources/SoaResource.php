<?php

namespace App\Http\Resources;

use App\Enums\BillType;
use App\Enums\Server;
use App\Enums\SoaAging;
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
     * Transform the SOA into an array, selecting contract- or period-based coverage dates,
     * mapping bill type and status to labels/colors, resolving account/branch names, and
     * embedding the related SOA activities only when the relation is eager loaded.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (!$this->contract_date_from) {
            $startDate = $this->period_date_from;
            $endDate = $this->period_date_to;
        } else {
            $startDate = $this->contract_date_from;
            $endDate = $this->contract_date_to;
        }
        return [
            'id' => $this->id,
            'soa_number' => $this->soa_number,
            'billing_ref' => $this->billing_ref,
            'billing_ref_names' => $this->getBillingRefNames($this->billing_ref),
            'bill_type' => BillType::label((int) $this->bill_type),
            'created_at' => CommonHelper::formatDate($this->created_at),
            'due_date' => CommonHelper::formatDate($this->due_date),
            'due_in' => $this->formatDaysDue($this->due_date),
            'due_in_color' => $this->dueInColor($this->due_date),
            'period_date_from' => $this->period_date_from,
            'period_date_to' => $this->period_date_to,
            'utilization_coverage' => Str::upper(CommonHelper::formatDate($this->period_date_from) . ' TO ' . CommonHelper::formatDate($this->period_date_to)),
            'period_coverage' => Str::upper(CommonHelper::formatDate($startDate) . ' TO ' . CommonHelper::formatDate($endDate)),
            'contract_date_from' => $this->contract_date_from,
            'contract_date_to' => $this->contract_date_to,
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

    /**
     * Flatten the billing reference values into a comma-separated string.
     *
     * @param mixed $billingRef Iterable of billing reference values, or empty.
     * @return string
     */
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
     * Aging-bucket label for a due date (single source of truth: {@see SoaAging::classify()}).
     *
     * @param string|null $date The due date to classify.
     * @return string|null Null when no due date; otherwise the aging bucket label
     *   (e.g. "Due (Current Month)", "Past Due – 30 Days").
     */
    public function formatDaysDue($date)
    {
        if (!$date) {
            return null;
        }

        return SoaAging::label(SoaAging::classify(Carbon::parse($date)));
    }

    /**
     * Aging-bucket color classes for a due date, used to style the "Due In" badge.
     *
     * @param string|null $date The due date to classify.
     * @return string|null Null when no due date; otherwise semantic color utility classes.
     */
    public function dueInColor($date)
    {
        if (!$date) {
            return null;
        }

        return SoaAging::color(SoaAging::classify(Carbon::parse($date)));
    }

    /**
     * Resolve the account name for the given account code from the HMS database.
     *
     * @param string $accountCode
     * @return string
     */
    public function getAccountName($accountCode) {
        $account = (new SqlDatabase(Server::HMS))->getAccount($accountCode);

        return $account->ac_name;
    }

    /**
     * Resolve and format the account expiry date for the given account code from the HMS database.
     *
     * @param string $accountCode
     * @return string|null
     */
    public function getAccountExpiryDate($accountCode) {
        $account = (new SqlDatabase(Server::HMS))->getAccount($accountCode);

        return CommonHelper::formatDate($account->expiry_date);
    }

    /**
     * Resolve the branch name for the given branch code from the HMS database.
     *
     * @param string $branchCode
     * @return string
     */
    public function getBranchName($branchCode) {
        $branch = (new SqlDatabase(Server::HMS))->getBranch($branchCode);

        return $branch->br_branch_name ?? '';
    }
}
