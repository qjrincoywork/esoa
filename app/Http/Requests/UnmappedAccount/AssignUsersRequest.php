<?php

namespace App\Http\Requests\UnmappedAccount;

use App\Enums\AccountCodePrefix;
use App\Enums\MappingEligibility;
use App\Enums\Server;
use App\Enums\UserType;
use App\Helpers\SqlDatabase;
use App\Http\Requests\Concerns\AuthorizesRoutePermission;
use App\Http\Requests\Concerns\ReadsMappingTargets;
use App\Models\User;
use App\Models\UserAccount;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Assign users" on the unmapped listing: map the selected accounts/branches onto the
 * chosen users, written to `user_accounts` by {@see UserAccount::grantToUsers()}.
 *
 * Nothing the client sends is taken on trust. Every row is checked against HMS — the
 * account exists, a branch belongs to the account it was sent with, and the account is
 * not a class users are never given ({@see AccountCodePrefix::excludedFromUserAccess()})
 * — and every user against the same rule the picker showed them under
 * ({@see MappingEligibility}), so a stale or hand-made request fails the way the screen
 * would have.
 */
class AssignUsersRequest extends FormRequest
{
    use AuthorizesRoutePermission;
    use ReadsMappingTargets;

    /**
     * The chosen users, resolved once per request.
     *
     * @var Collection<int, User>|null
     */
    private ?Collection $users = null;

    /**
     * Validate the selected rows and the chosen users.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->targetRules(),
            'user_ids' => ['required', 'array', 'min:1', 'max:' . config('vc.max_per_pages')],
            'user_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * Check the rows against HMS and the users against their mapping rules — only once
     * the shape is valid, since both checks read what the shape guarantees.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->rejectUnknownTargets($validator);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->rejectIneligibleUsers($validator);
        });
    }

    /**
     * Flag rows HMS does not know, branches sent with an account they do not belong to,
     * and accounts of a class users are never given. One lookup per directory, however
     * many rows.
     */
    private function rejectUnknownTargets(Validator $validator): void
    {
        $targets = $this->validated('targets');
        $hms = new SqlDatabase(Server::HMS);

        $accounts = $hms->getAccountNamesByCodes(array_map(
            static fn (array $target): string => trim((string) $target['account_code']),
            $targets
        ));
        $branchAccounts = $hms->getBranchAccountCodesByCodes(array_map(
            static fn (array $target): string => trim((string) ($target['branch_code'] ?? '')),
            $targets
        ));

        foreach ($targets as $index => $target) {
            $accountCode = trim((string) $target['account_code']);
            $branchCode = trim((string) ($target['branch_code'] ?? ''));

            if (in_array(AccountCodePrefix::of($accountCode), AccountCodePrefix::excludedFromUserAccess(), true)) {
                $validator->errors()->add("targets.{$index}.account_code", "Account {$accountCode} is not one users are given access to.");
            } elseif (!$accounts->has($accountCode)) {
                $validator->errors()->add("targets.{$index}.account_code", "Account {$accountCode} was not found in HMS.");
            } elseif ($branchCode !== '' && $branchAccounts->get($branchCode) !== $accountCode) {
                $validator->errors()->add("targets.{$index}.branch_code", "Branch {$branchCode} does not belong to account {$accountCode}.");
            }
        }
    }

    /**
     * Flag users who are gone, or who could not take every selected row: types that are
     * not mapped at all, users who already hold them all, and users the rows would take
     * past their type's cap.
     */
    private function rejectIneligibleUsers(Validator $validator): void
    {
        $ids = array_map('intval', $this->validated('user_ids'));
        $users = $this->users();

        if ($users->count() !== count($ids)) {
            $validator->errors()->add('user_ids', 'One or more of the selected users no longer exist.');

            return;
        }

        $keys = $this->targetKeys();
        $summary = UserAccount::mappingSummaryFor($ids, $keys);
        $refused = [];

        foreach ($users as $user) {
            $type = $user->userDetail?->type;
            $eligibility = MappingEligibility::resolve($type, $summary[$user->id]['mapped'], $summary[$user->id]['held'], count($keys));

            if (!MappingEligibility::isAssignable($eligibility)) {
                $refused[] = $this->refusal($user, $eligibility, $type);
            }
        }

        if ($refused !== []) {
            $validator->errors()->add('user_ids', implode(' ', $refused));
        }
    }

    /**
     * Why one user cannot take the rows, in a sentence naming them.
     *
     * @param  int|string|null  $type
     */
    private function refusal(User $user, string $eligibility, $type): string
    {
        $limit = UserType::accountMappingLimit($type);

        return match ($eligibility) {
            MappingEligibility::ALREADY_MAPPED => "{$user->username} already has every selected row.",
            MappingEligibility::LIMIT_REACHED => "{$user->username} (" . UserType::label((int) $type) . ') may hold '
                . ($limit === 1 ? 'a single mapping' : "at most {$limit} mappings") . '.',
            default => "{$user->username} (" . UserType::label((int) $type) . ') is not mapped to accounts and branches.',
        };
    }

    /**
     * The chosen users, with the detail their type is read from.
     *
     * @return Collection<int, User>
     */
    public function users(): Collection
    {
        return $this->users ??= User::with('userDetail')
            ->whereKey(array_map('intval', (array) $this->validated('user_ids')))
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'targets.required' => 'Select at least one account or branch to assign',
            'targets.max' => 'Assign at most :max accounts or branches at a time',
            'user_ids.required' => 'Select at least one user',
            'user_ids.max' => 'Assign to at most :max users at a time',
            'user_ids.*.distinct' => 'A user was selected more than once',
        ];
    }
}
