<?php

namespace App\Http\Requests\CashflowImport;

use Illuminate\Foundation\Http\FormRequest;

class ImportPasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => [
                'required',
                'string',
                'max:200000', // ~200KB to guard against overly large clipboard pastes
            ],
        ];
    }
}
