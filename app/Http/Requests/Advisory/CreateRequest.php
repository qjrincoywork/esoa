<?php

namespace App\Http\Requests\Advisory;

use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
{
    /**
     * Authorize superadmins or users holding the 'advisories.store' permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasRole('superadmin') ||
            $user->hasAnyPermission(['advisories.store'])
        );
    }

    /**
     * Validation rules for creating an advisory.
     *
     * An advisory is an image, a message, or both — one of the two is required, since a
     * pop-up with only a title says nothing. The window is optional on both ends; when
     * both are given the end must come after the start.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'message' => [
                'nullable',
                'string',
                'max:' . config('vc.max_text_limit'),
                'required_without:image',
            ],
            'image' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:' . config('vc.max_file_size'),
                'required_without:message',
            ],
            ...$this->windowRules(),
        ];
    }

    /**
     * Rules for the switch and the display window, shared with {@see UpdateRequest}.
     *
     * @return array<string, array<int, string>>
     */
    protected function windowRules(): array
    {
        return [
            'starts_at' => [
                'nullable',
                'date',
            ],
            'ends_at' => array_merge(
                ['nullable', 'date'],
                // `after:starts_at` with no start would compare against the literal text.
                $this->filled('starts_at') ? ['after:starts_at'] : []
            ),
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Readable messages for the window and content rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required_without' => 'Provide an image or a message.',
            'image.required_without' => 'Provide an image or a message.',
            'ends_at.after' => 'The end must be after the start.',
        ];
    }

    /**
     * Field names as they read on the form.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'starts_at' => 'start',
            'ends_at' => 'end',
            'is_active' => 'enabled',
        ];
    }
}
