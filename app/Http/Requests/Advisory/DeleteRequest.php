<?php

namespace App\Http\Requests\Advisory;

use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    /**
     * Authorize superadmins or users holding the 'advisories.destroy' permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasRole('superadmin') ||
            $user->hasAnyPermission(['advisories.destroy'])
        );
    }

    /**
     * Validation rules for deleting (or restoring) an advisory.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'exists:advisories,id',
            ],
        ];
    }
}
