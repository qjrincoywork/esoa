<?php

namespace App\Http\Requests\Concern;

use App\Rules\IsUserAdmin;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    /**
     * Authorize superadmin/admin roles or users holding the 'concern.destroy' permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['concern.destroy'])
        );
    }

    /**
     * Validation rules for deleting a concern, ensuring the record exists and the user is an admin.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'exists:concerns,id',
                new IsUserAdmin()
            ]
        ];
    }
}
