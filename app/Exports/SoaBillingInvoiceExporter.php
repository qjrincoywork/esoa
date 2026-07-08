<?php

namespace App\Exports;

use App\Enums\AccountType;
use App\Enums\Server;
use App\Enums\SoaStatus;
use App\Helpers\CommonHelper;
use App\Helpers\SqlDatabase;
use App\Models\Soa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\{Response, StreamedResponse};

class SoaBillingInvoiceExporter
{
    /** @var array<string, string> */
    protected array $accountNameCache = [];

    /** @var array<string, string> */
    protected array $branchNameCache = [];

    /**
     * Stream the given SOA query as a downloadable Excel-compatible (HTML table) spreadsheet.
     *
     * Rows are emitted lazily via a cursor so large result sets are exported without
     * loading every SOA into memory at once.
     *
     * @param  Builder  $query  The SOA query whose results become spreadsheet rows.
     * @param  string  $filename  Download filename sent to the browser.
     */
    public function download(Builder $query, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            echo $this->spreadsheetOpen();
            foreach ($query->cursor() as $soa) {
                echo $this->rowHtml($soa);
            }
            echo '</table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Build the opening HTML/Excel markup, including the header row derived from
     * the config('vc.billing_invoice_export_headers') column labels.
     */
    protected function spreadsheetOpen(): string
    {
        $headers = config('vc.billing_invoice_export_headers');

        $headerCells = '';
        foreach ($headers as $header) {
            $headerCells .= '<th>' . $this->escape($header) . '</th>';
        }

        return '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">'
            . '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
            . '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>'
            . '<x:Name>Billing Invoices</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>'
            . '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>'
            . '<body><table border="1"><thead><tr>'
            . $headerCells
            . '</tr></thead><tbody>';
    }

    /**
     * Render a single SOA as one HTML table row.
     *
     * Maps the SOA to the exporter's fixed column order: SOA number, account code
     * and name, branch code and name, account type label, billing reference(s),
     * created/due dates, days-due status, formatted amount, status label,
     * period from/to dates, and contract start/end dates.
     */
    protected function rowHtml(Soa $soa): string
    {
        $billingRef = is_array($soa->billing_ref)
            ? implode(', ', $soa->billing_ref)
            : (string) ($soa->billing_ref ?? '');

        $cells = [
            $soa->soa_number,
            $soa->account_code,
            $this->accountName($soa->account_code),
            $soa->branch_code,
            $this->branchName($soa->branch_code),
            AccountType::label($soa->account_type),
            $billingRef,
            CommonHelper::formatDate($soa->created_at),
            CommonHelper::formatDate($soa->due_date),
            $this->formatDaysDue($soa->due_date),
            number_format((float) $soa->amount, 2, '.', ''),
            SoaStatus::label((int) $soa->status),
            CommonHelper::formatDate($soa->period_date_from),
            CommonHelper::formatDate($soa->period_date_to),
            CommonHelper::formatDate($this->contractStartDate($soa->account_code)),
            CommonHelper::formatDate($this->contractEndDate($soa->account_code)),
        ];

        $row = '<tr>';
        foreach ($cells as $cell) {
            $row .= '<td>' . $this->escape((string) $cell) . '</td>';
        }

        return $row . '</tr>';
    }

    /**
     * Resolve the account's contract start date, preferring effectivity date over
     * renewal date, looked up from the HMS account record.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  Aborts 404 when the account code is empty or the account is not found.
     */
    protected function contractStartDate(?string $accountCode): string
    {
        if (empty($accountCode)) {
            abort(Response::HTTP_NOT_FOUND);
        }
        $account = (new SqlDatabase(Server::HMS))->getAccount($accountCode);
        if (empty($account)) {
            abort(Response::HTTP_NOT_FOUND);
        }
        $effectivityDate = CommonHelper::formatDate($account->ac_effdate);
        $renewalDate = CommonHelper::formatDate($account->ac_rendate);
        $startDate = $effectivityDate ?? $renewalDate;

        return $startDate;
    }

    /**
     * Resolve the account's contract end date, preferring cancellation date over
     * expiry date, looked up from the HMS account record.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  Aborts 404 when the account code is empty or the account is not found.
     */
    protected function contractEndDate(?string $accountCode): string
    {
        if (empty($accountCode)) {
            abort(Response::HTTP_NOT_FOUND);
        }
        $account = (new SqlDatabase(Server::HMS))->getAccount($accountCode);
        if (empty($account)) {
            abort(Response::HTTP_NOT_FOUND);
        }
        $cancelDate = CommonHelper::formatDate($account->ac_candate);
        $expiryDate = CommonHelper::formatDate($account->ac_expiry);
        $endDate = $cancelDate ?? $expiryDate;

        return $endDate;
    }

    /**
     * Resolve and encoding-clean the HMS account name for the given code,
     * memoizing the lookup in a per-instance cache. Returns '' for an empty code.
     */
    protected function accountName(?string $accountCode): string
    {
        if (empty($accountCode)) {
            return '';
        }

        if (!array_key_exists($accountCode, $this->accountNameCache)) {
            $account = (new SqlDatabase(Server::HMS))->getAccount($accountCode);
            $this->accountNameCache[$accountCode] = CommonHelper::convertStringEncoding(
                $account->ac_name ?? ''
            );
        }

        return $this->accountNameCache[$accountCode];
    }

    /**
     * Resolve and encoding-clean the HMS branch name for the given code,
     * memoizing the lookup in a per-instance cache. Returns '' for an empty code.
     */
    protected function branchName(?string $branchCode): string
    {
        if (empty($branchCode)) {
            return '';
        }

        if (!array_key_exists($branchCode, $this->branchNameCache)) {
            $branch = (new SqlDatabase(Server::HMS))->getBranch($branchCode);
            $this->branchNameCache[$branchCode] = CommonHelper::convertStringEncoding(
                $branch->br_branch_name ?? ''
            );
        }

        return $this->branchNameCache[$branchCode];
    }

    /**
     * Produce a human-readable due-status label for a due date: 'Past Due',
     * 'Due Today', 'Due Tomorrow', or 'Due in N days'. Returns '' when no date.
     */
    protected function formatDaysDue(mixed $date): string
    {
        if (!$date) {
            return '';
        }

        $parsed = Carbon::parse($date);

        if ($parsed->isPast()) {
            return 'Past Due';
        }

        $days = (int) now()->diffInDays($parsed, true);

        return match ($days) {
            0 => 'Due Today',
            1 => 'Due Tomorrow',
            default => "Due in {$days} days",
        };
    }

    /**
     * HTML-escape a cell value, first neutralizing CSV/Excel formula-injection
     * prefixes (=, +, -, @, tab, CR, LF) by prepending a single quote.
     */
    protected function escape(string $value): string
    {
        // Neutralize formula-injection prefixes before HTML-escaping
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
            $value = "'" . $value;
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
