<?php

namespace App\Http\Requests\User;

use App\Enums\AccountType;
use App\Enums\UserType;
use App\Models\User;
use App\Models\UserAccount;
use Illuminate\Contracts\Validation\Validator;
use App\Http\Requests\Concerns\AuthorizesRoutePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The changes to one user's account/branch mappings, as the mapping tab saves them.
 *
 * The tab pages the user's saved mappings rather than holding them all, so it posts
 * what changed instead of the complete set: the pairs added, the pairs removed, and
 * whether everything stored was cleared first. Applied by
 * {@see UserAccount::applyChanges()}.
 */
class UpdateAccountMappingRequest extends FormRequest
{
    use AuthorizesRoutePermission;

    /**
     * The user being mapped, resolved once per request.
     *
     * @var User|null
     */
    private ?User $target = null;

    /**
     * Validate the target user and the submitted changes.
     *
     * Both lists must be present, even empty, so a client that forgets one is rejected
     * rather than read as "no change". A blank branch code means every branch of the
     * account, in both lists.
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
            'clear_existing' => [
                'nullable',
                'boolean',
            ],
            'added' => [
                'present',
                'array',
            ],
            'added.*.account_type' => [
                'nullable',
                'string',
                Rule::in(AccountType::getValues()),
            ],
            'added.*.account_code' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'added.*.branch_code' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'removed' => [
                'present',
                'array',
            ],
            'removed.*.account_code' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'removed.*.branch_code' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
        ];
    }

    /**
     * Apply the rules that depend on the target user rather than the payload alone:
     * an added pair may not repeat, the user's type must be one that mappings apply to,
     * and that type caps how many it may hold once the changes are applied.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $addedKeys = $this->rejectDuplicates($validator);
            $target = $this->target();

            if (!$target || $validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $target->userDetail?->type;

            if ($addedKeys !== [] && !UserType::allowsAccountMapping($type)) {
                $validator->errors()->add(
                    'added',
                    UserType::label((int) $type) . ' users are not mapped to accounts and branches.'
                );

                return;
            }

            $limit = UserType::accountMappingLimit($type);
            $resulting = count($this->resultingKeys($target, $addedKeys));

            if ($limit !== null && $resulting > $limit) {
                $validator->errors()->add(
                    'added',
                    'An ' . UserType::label((int) $type) . ' may hold '
                    . ($limit === 1 ? 'a single mapping' : "at most {$limit} mappings")
                    . ", but these changes would leave {$resulting}."
                );
            }
        });
    }

    /**
     * Flag any added account/branch pair submitted more than once.
     *
     * @return array<int, string> The distinct keys added.
     */
    private function rejectDuplicates(Validator $validator): array
    {
        $seen = [];

        foreach ((array) $this->input('added', []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = UserAccount::mappingKey($row['account_code'] ?? null, $row['branch_code'] ?? null);

            if (isset($seen[$key])) {
                $validator->errors()->add(
                    "added.{$index}.account_code",
                    'This account / branch combination is mapped more than once.'
                );

                continue;
            }

            $seen[$key] = true;
        }

        return array_keys($seen);
    }

    /**
     * The keys the user would hold once these changes are applied, counted the same way
     * {@see UserAccount::applyChanges()} applies them: what is kept of the stored set,
     * plus whatever was added that it did not already hold.
     *
     * @param  array<int, string>  $addedKeys
     * @return array<int, string>
     */
    private function resultingKeys(User $target, array $addedKeys): array
    {
        $kept = $this->clearsExisting()
            ? []
            : array_diff(UserAccount::mappedKeysFor($target), $this->removedKeys());

        return array_values(array_unique([...$kept, ...$addedKeys]));
    }

    /**
     * The user whose mappings are being changed, or null when the id is not valid.
     */
    public function target(): ?User
    {
        return $this->target ??= User::with('userDetail')->find($this->input('user_id'));
    }

    /**
     * Whether every stored mapping is dropped before the added ones are written.
     */
    public function clearsExisting(): bool
    {
        return (bool) ($this->validated()['clear_existing'] ?? false);
    }

    /**
     * The added pairs, ready for {@see UserAccount::applyChanges()}.
     *
     * @return array<int, array<string, mixed>>
     */
    public function added(): array
    {
        return $this->validated()['added'] ?? [];
    }

    /**
     * The removed pairs, as the keys {@see UserAccount::mappingKey()} builds.
     *
     * @return array<int, string>
     */
    public function removedKeys(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $row): string => UserAccount::mappingKey($row['account_code'] ?? null, $row['branch_code'] ?? null),
            $this->validated()['removed'] ?? []
        )));
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
            'added.present' => 'The added mappings are required',
            'added.array' => 'The added mappings must be an array',
            'added.*.account_type.in' => 'The selected account type is invalid',
            'added.*.account_code.required' => 'Every mapping needs an account',
            'removed.present' => 'The removed mappings are required',
            'removed.array' => 'The removed mappings must be an array',
            'removed.*.account_code.required' => 'Every removed mapping needs an account',
        ];
    }
}
