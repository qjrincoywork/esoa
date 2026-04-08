<?php

namespace App\Http\Resources;

use App\Helpers\CommonHelper;
use App\Models\SoaActivity;
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
            'created_at' => CommonHelper::formatDate($this->created_at),
        ];
    }

    protected function resolveEventLabel(): string
    {
        $event = (string) $this->event;

        return match ($event) {
            'amount_added' => 'Amount added',
            'amount_deducted' => 'Amount deducted',
            default => Str::headline(str_replace('_', ' ', $event)),
        };
    }

    protected function describeFrom(): string
    {
        $from = $this->from;
        if ($from === null || $from === [] || ! is_array($from)) {
            return '—';
        }

        return match ((string) $this->event) {
            'amount_added', 'amount_deducted' => $this->describeAmountBefore($from),
            default => $this->describeGenericSnapshot($from),
        };
    }

    protected function describeTo(): string
    {
        $to = $this->to;
        if (! is_array($to) || $to === []) {
            return '—';
        }

        return match ((string) $this->event) {
            'amount_added', 'amount_deducted' => $this->describeAmountAfter($to),
            default => $this->describeGenericSnapshot($to),
        };
    }

    /**
     * @param  array<string, mixed>  $from
     */
    protected function describeAmountBefore(array $from): string
    {
        if (! isset($from['amount'])) {
            return $this->describeGenericSnapshot($from);
        }

        $formatted = number_format((float) $from['amount'], 2);

        return "Balance before this change: {$formatted}.";
    }

    /**
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
     * @param  array<string, mixed>  $data
     */
    protected function describeGenericSnapshot(array $data): string
    {
        $segments = [];
        foreach ($data as $key => $value) {
            $label = Str::headline(str_replace('_', ' ', (string) $key));
            if (is_array($value)) {
                $segments[] = "{$label}: ".json_encode($value);
            } elseif (is_bool($value)) {
                $segments[] = "{$label}: ".($value ? 'Yes' : 'No');
            } elseif (is_numeric($value) && str_contains((string) $key, 'amount')) {
                $segments[] = "{$label}: ".number_format((float) $value, 2);
            } else {
                $segments[] = "{$label}: ".(string) $value;
            }
        }

        return $segments !== [] ? implode(' ', $segments) : '—';
    }
}
