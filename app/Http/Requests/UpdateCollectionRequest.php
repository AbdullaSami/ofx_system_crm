<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contract_id' => ['sometimes', 'exists:contracts,id'],
            'client_id' => ['sometimes', 'exists:clients,id'],

            'amount_due' => ['sometimes', 'numeric', 'min:0'],
            'amount_collected' => ['sometimes', 'numeric', 'min:0'],

            'due_date' => ['sometimes', 'date'],
            'collection_date' => ['nullable', 'date'],

            'status' => [
                'sometimes',
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
            'service_slug' => ['sometimes', 'string', 'exists:services,slug'],

        ];
    }
}
