<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'treasury_id' => ['sometimes', 'integer', 'exists:treasury_accounts,id'],
            'expense_type' => ['sometimes', Rule::in([
                Expense::TYPE_WAGE,
                Expense::TYPE_REFUND,
                Expense::TYPE_GENERAL,
                Expense::TYPE_PAY_BILL,
            ])],

            'expensable_id' => [
                'required_if:expense_type,' . Expense::TYPE_WAGE . ',' . Expense::TYPE_REFUND,
                'integer',
            ],

            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'expense_date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],

            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
