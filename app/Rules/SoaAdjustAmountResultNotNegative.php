<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\SoaAmountOperation;
use App\Models\Soa;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ensures deduct (or add) does not leave the SOA amount below zero.
 */
class SoaAdjustAmountResultNotNegative implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $soaId = $this->data['soa_id'] ?? null;
        $operation = $this->data['operation'] ?? null;

        if (! is_numeric($soaId) || $operation === null || $operation === '' || ! is_numeric($value)) {
            return;
        }

        $soa = Soa::query()->find((int) $soaId);
        if (! $soa) {
            return;
        }

        $current = (float) $soa->amount;
        $delta = (float) $value;
        $new = $operation === SoaAmountOperation::ADD
            ? round($current + $delta, 2)
            : round($current - $delta, 2);

        if ($new < 0) {
            $fail('Resulting amount cannot be negative.');
        }
    }
}
