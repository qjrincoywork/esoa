<?php

namespace App\Http\Resources;

use App\Enums\AccountType;
use App\Enums\BillType;
use App\Enums\SoaStatus;
use App\Helpers\CommonHelper;
use App\Models\SoaActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * SOA activity row for lists: human-readable {@see SoaActivity::$from} / {@see SoaActivity::$to} (JSON) payloads.
 *
 * @mixin SoaActivity
 */
class SoaActivityListResource extends JsonResource
{
    /**
     * Transform the SOA activity into a list row with a human-readable event label and
     * narrative descriptions of the before ("from") and after ("to") snapshots.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'event' => $this->event,
            'event_label' => $this->resolveEventLabel(),
            'from' => $this->describeFrom(),
            'to' => $this->describeTo(),
            'created_at' => CommonHelper::formatDate($this->created_at, true),
        ];
    }

    /**
     * Map the raw event key to a human-readable label, falling back to a headlined
     * version of the key for unknown events.
     */
    protected function resolveEventLabel(): string
    {
        $event = (string) $this->event;

        return match ($event) {
            'amount_added' => 'Added to SOA amount',
            'amount_deducted' => 'Deducted from SOA amount',
            'amount_adjusted' => 'SOA amount adjusted',
            'update' => 'SOA details updated',
            'billing_invoice_email_sent' => 'Billing invoice notification emailed',
            'create', 'created', 'store' => 'SOA record created',
            default => Str::headline(str_replace('_', ' ', $event)),
        };
    }

    /**
     * Build a narrative description of the "from" (before) snapshot, choosing a strategy
     * based on the event type. Returns an em dash when there is nothing to describe.
     */
    protected function describeFrom(): string
    {
        $from = $this->from;
        if ($from === null || $from === [] || ! is_array($from)) {
            return '—';
        }

        return match ((string) $this->event) {
            'amount_added', 'amount_deducted' => $this->describeAmountBefore($from),
            'update' => $this->describeSoaUpdateBefore($from, $this->toArraySafeTo()),
            default => $this->describeGenericSnapshot($from),
        };
    }

    /**
     * Build a narrative description of the "to" (after) snapshot, choosing a strategy
     * based on the event type. Returns an em dash when there is nothing to describe.
     */
    protected function describeTo(): string
    {
        $to = $this->to;
        if (! is_array($to) || $to === []) {
            return '—';
        }

        return match ((string) $this->event) {
            'amount_added', 'amount_deducted' => $this->describeAmountAfter($to),
            'update' => $this->describeSoaUpdateAfter($this->fromArraySafe(), $to),
            default => $this->describeGenericSnapshot($to),
        };
    }

    /**
     * Return the "from" snapshot as an array, or an empty array when it is not one.
     *
     * @return array<string, mixed>
     */
    protected function fromArraySafe(): array
    {
        $from = $this->from;

        return is_array($from) ? $from : [];
    }

    /**
     * Return the "to" snapshot as an array, or an empty array when it is not one.
     *
     * @return array<string, mixed>
     */
    protected function toArraySafeTo(): array
    {
        $to = $this->to;

        return is_array($to) ? $to : [];
    }

    /**
     * Describe the previous values of the SOA fields that changed during an update event.
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     */
    protected function describeSoaUpdateBefore(array $from, array $to): string
    {
        $keys = $this->changedFieldKeys($from, $to);
        if ($keys === []) {
            return 'No differences found between the previous record and the submitted data.';
        }

        $parts = [];
        foreach ($keys as $key) {
            $old = $from[$key] ?? null;
            $parts[] = sprintf(
                '%s was %s',
                $this->attributeLabel($key),
                $this->formatAttributeValue($key, $old)
            );
        }

        return implode('; ', $parts).'.';
    }

    /**
     * Describe the new values of the SOA fields that changed during an update event.
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     */
    protected function describeSoaUpdateAfter(array $from, array $to): string
    {
        $keys = $this->changedFieldKeys($from, $to);
        if ($keys === []) {
            return 'No differences found between the previous record and the submitted data.';
        }

        $parts = [];
        foreach ($keys as $key) {
            $new = $to[$key] ?? null;
            $parts[] = sprintf(
                '%s is now %s',
                $this->attributeLabel($key),
                $this->formatAttributeValue($key, $new)
            );
        }

        return implode('; ', $parts).'.';
    }

    /**
     * Keys present in {@see $to} that differ from {@see $from} (meaningful comparison).
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @return list<string>
     */
    protected function changedFieldKeys(array $from, array $to): array
    {
        $changed = [];
        foreach ($to as $key => $newVal) {
            if (! is_string($key) || $this->isIgnoredDiffKey($key)) {
                continue;
            }
            $oldVal = $from[$key] ?? null;
            if ($this->valuesMeaningfullyDiffer((string) $key, $oldVal, $newVal)) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * Determine whether a field key should be excluded from diffing, per the
     * vc.ignored_diff_keys config list.
     */
    protected function isIgnoredDiffKey(string $key): bool
    {
        return in_array($key, config('vc.ignored_diff_keys'), true);
    }

    /**
     * Determine whether two values differ once canonicalized for the given key.
     */
    protected function valuesMeaningfullyDiffer(string $key, mixed $old, mixed $new): bool
    {
        return $this->canonicalizeForCompare($key, $old) !== $this->canonicalizeForCompare($key, $new);
    }

    /**
     * Normalize a value for comparison so cosmetic differences (date formats, numeric
     * precision, file paths, numeric-string casts) are not treated as meaningful changes.
     */
    protected function canonicalizeForCompare(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (in_array($key, ['due_date', 'period_date_from', 'period_date_to'], true)) {
            try {
                return Carbon::parse((string) $value)->toDateString();
            } catch (\Throwable) {
                return trim((string) $value);
            }
        }

        if (in_array($key, ['amount', 'amount_paid', 'payment_adjustment', 'balance'], true) && is_numeric($value)) {
            return (string) round((float) $value, 2);
        }

        if (in_array($key, ['file_pdf', 'file_xls'], true) && is_string($value)) {
            $base = basename(str_replace('\\', '/', $value));

            return $base !== '' ? $base : trim($value);
        }

        if (is_numeric($value) && in_array($key, ['bill_type', 'status', 'id', 'user_id'], true)) {
            return (string) (int) $value;
        }
        $value = is_array($value) ? implode(', ', $value) : $value;

        return trim((string) $value);
    }

    /**
     * Map a SOA field key to its human-readable label, falling back to a headlined key.
     */
    protected function attributeLabel(string $key): string
    {
        return match ($key) {
            'soa_number' => 'SOA number',
            'account_type' => 'Account type',
            'account_code' => 'Account code',
            'branch_code' => 'Branch code',
            'billing_ref' => 'Billing reference',
            'bill_type' => 'Bill type',
            'status' => 'Status',
            'due_date' => 'Due date',
            'period_date_from' => 'Period start',
            'period_date_to' => 'Period end',
            'amount' => 'Amount',
            'amount_paid' => 'Amount paid',
            'payment_adjustment' => 'Payment adjustment',
            'balance' => 'Balance',
            'file_pdf' => 'PDF file',
            'file_xls' => 'Excel file',
            'notified_email' => 'Notification sent to',
            'user_id' => 'Assigned user',
            default => Str::headline(str_replace('_', ' ', $key)),
        };
    }

    /**
     * Human-readable value for a single SOA attribute (lists, audit text, generic snapshots).
     */
    protected function formatAttributeValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            if ($value === []) {
                return '(empty)';
            }

            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

            return $encoded !== false ? $encoded : '(complex value)';
        }

        if (in_array($key, ['file_pdf', 'file_xls'], true) && is_string($value)) {
            $base = basename(str_replace('\\', '/', $value));

            return $base !== '' ? $base : $value;
        }

        if (in_array($key, ['due_date', 'period_date_from', 'period_date_to', 'created_at', 'updated_at', 'deleted_at'], true)) {
            $formatted = CommonHelper::formatDate((string) $value);

            return $formatted ?? (string) $value;
        }

        if ($key === 'bill_type' && is_numeric($value)) {
            $int = (int) $value;
            if (in_array($int, BillType::getValues(), true)) {
                return BillType::label($int);
            }
        }

        if ($key === 'status' && is_numeric($value)) {
            $int = (int) $value;
            if (in_array($int, SoaStatus::getValues(), true)) {
                return SoaStatus::label($int);
            }
        }

        if ($key === 'account_type') {
            $s = (string) $value;
            if (in_array($s, AccountType::getValues(), true)) {
                return AccountType::label($s).' ('.$s.')';
            }
        }

        if (($key === 'amount' || str_contains($key, 'amount') || in_array($key, ['payment_adjustment', 'balance'], true)) && is_numeric($value)) {
            return number_format((float) $value, 2);
        }

        return (string) $value;
    }

    /**
     * Describe the balance prior to an amount added/deducted event, falling back to a
     * generic snapshot when no amount is present.
     *
     * @param  array<string, mixed>  $from
     */
    protected function describeAmountBefore(array $from): string
    {
        if (! isset($from['amount'])) {
            return $this->describeGenericSnapshot($from);
        }

        $formatted = number_format((float) $from['amount'], 2);

        return "Balance before this change was {$formatted}.";
    }

    /**
     * Describe the new balance and applied delta after an amount added/deducted event,
     * falling back to a generic snapshot when neither is present.
     *
     * @param  array<string, mixed>  $to
     */
    protected function describeAmountAfter(array $to): string
    {
        $parts = [];

        if (isset($to['amount'])) {
            $parts[] = 'New balance: '.number_format((float) $to['amount'], 2).'.';
        }

        if (isset($to['delta'])) {
            $delta = number_format((float) $to['delta'], 2);
            $op = isset($to['operation_label']) && is_string($to['operation_label'])
                ? $to['operation_label']
                : 'Change';
            $parts[] = "{$op}: {$delta}.";
        }

        return $parts !== []
            ? implode(' ', $parts)
            : $this->describeGenericSnapshot($to);
    }

    /**
     * Build a generic "label: value" description of an arbitrary snapshot, skipping
     * ignored diff keys.
     *
     * @param  array<string, mixed>  $data
     */
    protected function describeGenericSnapshot(array $data): string
    {
        $segments = [];
        foreach ($data as $key => $value) {
            if (! is_string($key) || $this->isIgnoredDiffKey($key)) {
                continue;
            }
            $label = $this->attributeLabel($key);
            if (is_array($value)) {
                $segments[] = "{$label}: ".($this->formatAttributeValue($key, $value));
            } else {
                $segments[] = "{$label}: ".$this->formatAttributeValue($key, $value);
            }
        }

        return $segments !== [] ? implode(' ', $segments) : '—';
    }
}
