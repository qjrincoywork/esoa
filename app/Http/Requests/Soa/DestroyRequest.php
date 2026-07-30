<?php

namespace App\Http\Requests\Soa;

use App\Rules\IsUserAdmin;
use Illuminate\Foundation\Http\FormRequest;

class DestroyRequest extends FormRequest
{
    /**
     * Authorize superadmin/admin roles or users holding the "soas.destroy" permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['soas.destroy'])
        );
    }

    /**
     * Validate that the id references an existing SOA and that the acting user is an admin.
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'exists:soas,id',
                new IsUserAdmin()
            ],
        ];
    }

    /**
     * Custom validation messages for the SOA id rules.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'SOA ID is required.',
            'id.integer'  => 'SOA ID must be an integer.',
        ];
    }
}
