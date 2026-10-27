<?php

namespace App\Http\Requests\Advisory;

use App\Models\Advisory;

class UpdateRequest extends CreateRequest
{
    /**
     * Authorize superadmins or users holding the 'advisories.update' permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasRole('superadmin') ||
            $user->hasAnyPermission(['advisories.update'])
        );
    }

    /**
     * Validation rules for updating an advisory.
     *
     * The image is optional here — leaving the file input empty keeps the current one —
     * but an advisory that has no image yet still needs a message.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasStoredImage = filled(Advisory::query()->whereKey($this->input('id'))->value('image'));

        return [
            'id' => [
                'required',
                'integer',
                'exists:advisories,id',
            ],
            'title' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'message' => array_merge(
                ['nullable', 'string', 'max:' . config('vc.max_text_limit')],
                $hasStoredImage ? [] : ['required_without:image']
            ),
            'image' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:' . config('vc.max_file_size'),
            ],
            ...$this->windowRules(),
        ];
    }
}
