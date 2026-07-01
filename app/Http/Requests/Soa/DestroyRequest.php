<?php

namespace App\Http\Requests\Soa;

use App\Rules\IsDataExists;
use Illuminate\Foundation\Http\FormRequest;

class DestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['soas.destroy'])
        );
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', new IsDataExists('soas')],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'SOA ID is required.',
            'id.integer'  => 'SOA ID must be an integer.',
        ];
    }
}
