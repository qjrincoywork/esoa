<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'id' => $this->up_id,
            'soanum' => $this->up_soanum,
            'macode' => $this->up_macode,
            'refid' => $this->up_refid,
            'upcode' => $this->up_accode,
            'billcode' => $this->up_billcode,
            'billtype' => $this->up_billtype,
            'billdate' => $this->formatDate($this->up_billdate),
            'upload_date' => $this->formatDate($this->up_date, true),
            'due_date' => $this->formatDate($this->up_due_date),
            'period_coverage' => $this->up_period_cov,
            'paid_date' => $this->formatDate($this->up_status_date),
            'amount_due' => number_format($this->up_amount, 2),
            'company_branch' => $this->up_acname ?? $this->up_branch,
            'file_pdf' => $this->up_filepdf,
            'file_xls' => $this->up_filexls,
            'status' => $this->status(),
        ];
    }

    /**
     * Get the status of the UP account
     *
     * @return string
     *   'Unpaid' if the account is unpaid and not endorsed
     *   'Endorsed' if the account is unpaid and endorsed
     *   'Paid' if the account is paid
     */
    public function status(): string
    {
        $status = (int) $this->up_status;
        $endorsed = (int) $this->up_endorsedtoacct;

        return match (true) {
            $status === 0 && $endorsed === 0 => 'Unpaid',
            $status === 0 && $endorsed === 1 => 'Endorsed',
            default => 'Paid',
        };
    }

    /**
     * Format a date string according to the given parameters.
     *
     * @param  string|null  $date
     * @param  bool  $withTime
     * @return  string|null
     */
    public function formatDate($date, $withTime = false)
    {
        if (!$date) {
            return null;
        }

        $date = Carbon::parse($date);

        return $withTime
            ? $date->format('F j, Y')
            : $date->format('F j, Y h:i A');
    }
}
