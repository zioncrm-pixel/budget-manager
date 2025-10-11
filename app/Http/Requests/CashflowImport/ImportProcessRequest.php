<?php

namespace App\Http\Requests\CashflowImport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mapping = $this->input('mapping', []);

        $mapping['type'] ??= [];
        $mapping['type']['mode'] = $mapping['type']['mode'] ?? 'auto_from_amount';

        $mapping['posting_date'] ??= [];
        $mapping['posting_date']['mode'] = $mapping['posting_date']['mode'] ?? 'same_as_transaction';

        $this->merge([
            'excluded_rows' => $this->input('excluded_rows', []),
            'row_assignments' => $this->input('row_assignments', []),
            'defaults' => $this->input('defaults', []),
            'mapping' => $mapping,
        ]);
    }

    public function rules(): array
    {
        $user = $this->user();
        $userId = $user?->id ?? 0;

        return [
            'import_id' => ['required', 'uuid'],

            'header_row_index' => ['nullable', 'integer', 'min:0'],
            'excluded_rows' => ['present', 'array'],
            'excluded_rows.*' => ['integer', 'min:0'],

            'mapping' => ['required', 'array'],

            'mapping.date' => ['required', 'array'],
            'mapping.date.column' => ['required', 'integer', 'min:0'],
            'mapping.date.format' => ['nullable', 'string', 'max:32'],

            'mapping.description' => ['required', 'array'],
            'mapping.description.column' => ['required', 'integer', 'min:0'],

            'mapping.amount' => ['required', 'array'],
            'mapping.amount.mode' => ['required', Rule::in(['single', 'split'])],
            'mapping.amount.column' => ['required_if:mapping.amount.mode,single', 'nullable', 'integer', 'min:0'],
            'mapping.amount.debit_column' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn () => $this->input('mapping.amount.mode') === 'split' && $this->input('mapping.amount.credit_column') === null),
            ],
            'mapping.amount.credit_column' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn () => $this->input('mapping.amount.mode') === 'split' && $this->input('mapping.amount.debit_column') === null),
            ],
            'mapping.amount.negate' => ['nullable', 'boolean'],

            'mapping.type' => ['nullable', 'array'],
            'mapping.type.mode' => ['required_with:mapping.type', Rule::in(['auto_from_amount', 'fixed', 'column'])],
            'mapping.type.column' => ['required_if:mapping.type.mode,column', 'nullable', 'integer', 'min:0'],
            'mapping.type.fixed_value' => ['required_if:mapping.type.mode,fixed', 'nullable', Rule::in(['income', 'expense'])],
            'mapping.type.income_values' => ['nullable', 'array'],
            'mapping.type.income_values.*' => ['nullable', 'string', 'max:255'],
            'mapping.type.expense_values' => ['nullable', 'array'],
            'mapping.type.expense_values.*' => ['nullable', 'string', 'max:255'],

            'mapping.posting_date' => ['nullable', 'array'],
            'mapping.posting_date.mode' => ['required_with:mapping.posting_date', Rule::in(['same_as_transaction', 'column', 'fixed'])],
            'mapping.posting_date.column' => ['required_if:mapping.posting_date.mode,column', 'nullable', 'integer', 'min:0'],
            'mapping.posting_date.value' => ['required_if:mapping.posting_date.mode,fixed', 'nullable', 'string', 'max:64'],
            'mapping.posting_date.format' => ['nullable', 'string', 'max:32'],

            'mapping.reference' => ['nullable', 'array'],
            'mapping.reference.column' => ['nullable', 'integer', 'min:0'],

            'mapping.notes' => ['nullable', 'array'],
            'mapping.notes.column' => ['nullable', 'integer', 'min:0'],

            'defaults' => ['present', 'array'],
            'defaults.category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'defaults.cash_flow_source_id' => [
                'nullable',
                Rule::exists('cash_flow_sources', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],

            'row_assignments' => ['present', 'array'],
            'row_assignments.*.category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'row_assignments.*.cash_flow_source_id' => [
                'nullable',
                Rule::exists('cash_flow_sources', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'row_assignments.*.notes' => ['nullable', 'string'],
        ];
    }
}
