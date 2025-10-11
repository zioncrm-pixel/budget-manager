<?php

namespace App\Http\Requests\CashflowImport;

use Illuminate\Foundation\Http\FormRequest;

class ImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:20480', // 20MB
                'mimes:xlsx,xls,csv,txt,xlsm',
            ],
        ];
    }
}
