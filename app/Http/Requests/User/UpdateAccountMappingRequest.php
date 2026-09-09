<?php

namespace App\Http\Requests\User;

use App\Enums\AccountType;
use App\Enums\UserType;
use App\Models\User;
use App\Models\UserAccount;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountMappingRequest extends FormRequest
{
    /**
     * The user being mapped, resolved once per request.
     *
     * @var User|null
     */
    private ?User $target = null;

    /**
     * Authorize only users holding the "superadmin" role.
     *
     * Runs before the controller opens its transaction, matching the other
     * user-administration requests.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    /**
     * Validate the target user and the submitted account/branch mapping set.
     *
     * The set is the complete intended state, so an empty array is valid and means
     * "revoke every mapping"; a missing key is rejected rather than silently read as
     * a revocation. A blank branch code maps every branch of the account.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'user_accounts' => [
                'present',
                'array',
            ],
            'user_accounts.*.account_type' => [
                'nullable',
                'string',
                Rule::in(AccountType::getValues()),
            ],
            'user_accounts.*.account_code' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'user_accounts.*.branch_code' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
        ];
    }

    /**
     * Apply the rules that depend on the target user rather than the payload alone:
     * a mapping may not repeat, the user's type must be one that mappings apply to,
     * and that type caps how many it may hold.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $unique = $this->rejectDuplicates($validator);
            $target = $this->target();

            if (!$target) {
                return;
            }

            $type = $target->userDetail?->type;

            if ($unique > 0 && !UserType::allowsAccountMapping($type)) {
                $validator->errors()->add(
                    'user_accounts',
                    UserType::label((int) $type) . ' users are not mapped to accounts and branches.'
                );

                return;
            }

            $limit = UserType::accountMappingLimit($type);

            if ($limit !== null && $unique > $limit) {
                $validator->errors()->add(
                    'user_accounts',
                    'An ' . UserType::label((int) $type) . ' may hold '
                    . ($limit === 1 ? 'a single mapping' : "at most {$limit} mappings")
                    . ", but {$unique} were submitted."
                );
            }
        });
    }

    /**
     * Flag any account/branch pair submitted more than once.
     *
     * @return int Number of distinct mappings submitted.
     */
    private function rejectDuplicates(Validator $validator): int
    {
        $seen = [];

        foreach ((array) $this->input('user_accounts', []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = UserAccount::mappingKey(
                $row['account_code'] ?? null,
                $row['branch_code'] ?? null
            );

            if (isset($seen[$key])) {
                $validator->errors()->add(
                    "user_accounts.{$index}.account_code",
                    'This account / branch combination is mapped more than once.'
                );

                continue;
            }

            $seen[$key] = true;
        }

        return count($seen);
    }

    /**
     * The user whose mappings are being replaced, or null when the id is not valid.
     */
    public function target(): ?User
    {
        return $this->target ??= User::with('userDetail')->find($this->input('user_id'));
    }

    /**
     * The submitted mapping set, ready for {@see UserAccount::syncForUser()}.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mappings(): array
    {
        return $this->validated()['user_accounts'] ?? [];
    }

    /**
     * How many mappings the target user's type may hold; null when unlimited.
     */
    public function mappingLimit(): ?int
    {
        return UserType::accountMappingLimit($this->target()?->userDetail?->type);
    }

    /**
     * Custom validation messages for the mapping fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'The User field is required',
            'user_id.integer' => 'The User field must be an integer',
            'user_id.exists' => 'The User field must be an existing user',
            'user_accounts.present' => 'The account and branch mapping set is required',
            'user_accounts.array' => 'The account and branch mapping set must be an array',
            'user_accounts.*.account_type.in' => 'The selected account type is invalid',
            'user_accounts.*.account_code.required' => 'Every mapping needs an account',
        ];
    }
}
