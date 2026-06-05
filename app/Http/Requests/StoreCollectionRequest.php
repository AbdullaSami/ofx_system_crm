<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreCollectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
return [
            'contract_id' => ['required', 'exists:contracts,id'],
            'client_id' => ['required', 'exists:clients,id'],

            'amount_due' => ['required', 'numeric', 'min:0'],
            'amount_collected' => ['nullable', 'numeric', 'min:0'],

            'due_date' => ['required', 'date'],
            'collection_date' => ['nullable', 'date'],

            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'partial',
                    'paid',
                    'overdue',
                    'written_off',
                ]),
            ],

            'payment_method' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

        protected function prepareForValidation(): void
    {
        $this->merge([
            'amount_collected' => $this->amount_collected ?? 0,
            'status' => $this->status ?? 'pending',
        ]);
    }
}
