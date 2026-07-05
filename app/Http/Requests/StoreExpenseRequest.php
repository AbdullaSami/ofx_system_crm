<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'treasury_id' => ['required', 'integer', 'exists:treasury_accounts,id'],
            'expense_type' => ['required', Rule::in([
                Expense::TYPE_WAGE,
                Expense::TYPE_REFUND,
                Expense::TYPE_GENERAL,
                Expense::TYPE_PAY_BILL,
            ])],

            // Required only when the expense relates to an employee (wage) or a collection (refund).
            'expensable_id' => [
                'required_if:expense_type,' . Expense::TYPE_WAGE . ',' . Expense::TYPE_REFUND,
                'integer',
            ],

            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],

            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // 5MB each
        ];
    }

    public function messages(): array
    {
        return [
            'expensable_id.required_if' => 'Please select the related employee or collection for this expense type.',
        ];
    }
}
