<?php

namespace App\Http\Requests\Advisory;

use Illuminate\Foundation\Http\FormRequest;

class ToggleActiveRequest extends FormRequest
{
    /**
     * Authorize superadmins or users holding the 'advisories.toggle_active' permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasRole('superadmin') ||
            $user->hasAnyPermission(['advisories.toggle_active'])
        );
    }

    /**
     * Validate the target advisory and the switch position to set.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:advisories,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
