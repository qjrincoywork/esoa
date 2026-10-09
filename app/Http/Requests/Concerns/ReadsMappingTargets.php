<?php

namespace App\Http\Requests\Concerns;

use App\Enums\AccountType;
use App\Models\UserAccount;

/**
 * The accounts/branches a request is about to map onto users, as the unmapped listing
 * sends them: one `{account_code, branch_code}` pair per selected row, a blank branch
 * code standing for the whole account.
 *
 * Shared by the user picker and the assignment itself, so the two read the same rows
 * the same way — the picker's eligibility is only worth showing if the save agrees.
 */
trait ReadsMappingTargets
{
    /**
     * Validation rules for the targets. Capped at the largest page the listing serves,
     * which is the most rows one selection can hold.
     *
     * @return array<string, array<int, string>>
     */
    protected function targetRules(): array
    {
        $max = config('vc.max_string_limit');

        return [
            'targets' => ['required', 'array', 'min:1', 'max:' . config('vc.max_per_pages')],
            'targets.*' => ['required', 'array'],
            'targets.*.account_code' => ['required', 'string', 'max:' . $max],
            'targets.*.branch_code' => ['nullable', 'string', 'max:' . $max],
        ];
    }

    /**
     * The targets as storable pairs, deduplicated by {@see UserAccount::mappingKey()}.
     *
     * The account type is derived from the account code
     * ({@see AccountType::fromAccountCode()}), exactly as the listing classifies a row,
     * rather than taken from the client.
     *
     * @return array<int, array{account_type: string|null, account_code: string, branch_code: string|null}>
     */
    public function targetPairs(): array
    {
        $pairs = [];

        foreach ($this->validated('targets', []) as $target) {
            $accountCode = trim((string) ($target['account_code'] ?? ''));
            $branchCode = trim((string) ($target['branch_code'] ?? ''));

            $pairs[UserAccount::mappingKey($accountCode, $branchCode)] ??= [
                'account_type' => AccountType::fromAccountCode($accountCode),
                'account_code' => $accountCode,
                'branch_code' => $branchCode !== '' ? $branchCode : null,
            ];
        }

        return array_values($pairs);
    }

    /**
     * The targets' pair keys, deduplicated.
     *
     * @return array<int, string>
     */
    public function targetKeys(): array
    {
        return array_map(
            static fn (array $pair): string => UserAccount::mappingKey($pair['account_code'], $pair['branch_code']),
            $this->targetPairs()
        );
    }
}
