<?php

namespace App\Http\Requests\User;

use App\Enums\EmailNotification;
use App\Http\Requests\Concerns\AuthorizesRoutePermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Set which email notifications ({@see EmailNotification}) one or more users receive.
 *
 * One request serves the row action and the bulk action alike: a row sends a list of
 * one with every notification, a bulk change only the notifications it changes —
 * `settings` maps each to whether the users receive it, and anything left out stays as
 * it is. Authorized by the `users.email_notifications` permission.
 */
class UpdateEmailNotificationsRequest extends FormRequest
{
    use AuthorizesRoutePermission;

    /**
     * Validate a non-empty list of existing user IDs and a non-empty map of known
     * notifications to on/off.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids'   => ['required', 'array', 'min:1', 'max:' . config('vc.max_per_pages')],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'settings'   => ['required', 'array', 'min:1', 'array:' . implode(',', EmailNotification::getValues())],
            'settings.*' => ['required', 'boolean'],
        ];
    }

    /**
     * Custom validation messages for the email-notification settings.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required'   => 'Please select at least one user.',
            'user_ids.array'      => 'Users must be provided as an array.',
            'user_ids.min'        => 'Please select at least one user.',
            'user_ids.max'        => 'Too many users selected at once.',
            'user_ids.*.integer'  => 'Each user ID must be an integer.',
            'user_ids.*.distinct' => 'A user was selected more than once.',
            'user_ids.*.exists'   => 'One or more selected users do not exist.',
            'settings.required'   => 'Choose at least one notification to change.',
            'settings.min'        => 'Choose at least one notification to change.',
            'settings.array'      => 'One or more notifications do not exist.',
            'settings.*.required' => 'Each notification must be turned on or off.',
            'settings.*.boolean'  => 'Each notification must be turned on or off.',
        ];
    }

    /**
     * The users to apply the settings to.
     *
     * @return array<int, int>
     */
    public function userIds(): array
    {
        return array_map('intval', $this->validated('user_ids'));
    }

    /**
     * Each notification to change, mapped to whether the users receive it.
     *
     * @return array<string, bool>
     */
    public function settings(): array
    {
        return array_map(
            static fn ($receives): bool => filter_var($receives, FILTER_VALIDATE_BOOLEAN),
            $this->validated('settings')
        );
    }
}
