<?php

namespace App\Http\Requests\NavigationModule;

use App\Enums\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRequest extends FormRequest
{
    /**
     * Validation rules for creating a navigation module (name, unique slug, url, icon, parent navigation, optional permission, colour, parent module, order and status).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:191'],
            'slug'          => ['required', 'string', 'max:191', 'unique:navigation_modules,slug'],
            'url'           => ['nullable', 'string', 'max:500'],
            'icon'          => ['nullable', 'string', 'max:191'],
            'navigation_id' => ['required', 'integer', 'exists:navigations,id'],
            'permission_id' => ['nullable', 'integer', 'exists:permissions,id'],
            'color'         => ['nullable', 'string', 'max:100'],
            'ref_id'        => ['nullable', 'integer', 'exists:navigation_modules,id'],
            'order_number'  => ['nullable', 'integer', 'min:0'],
            'status'        => ['nullable', 'integer', Rule::in(Status::getValues())],
        ];
    }

    /**
     * Custom validation messages for navigation module creation.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'          => 'Module name is required.',
            'slug.required'          => 'Slug is required.',
            'slug.unique'            => 'This slug is already in use.',
            'navigation_id.required' => 'A navigation must be selected.',
            'navigation_id.exists'   => 'The selected navigation does not exist.',
            'permission_id.exists'   => 'The selected permission does not exist.',
            'ref_id.exists'          => 'The selected parent module does not exist.',
        ];
    }
}
