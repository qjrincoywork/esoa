<?php

namespace App\Http\Requests\OfficialReceipt;

use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:official_receipts,id',
        ];
    }
}
