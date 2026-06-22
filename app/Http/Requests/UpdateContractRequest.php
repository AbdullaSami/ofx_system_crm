<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContractRequest extends FormRequest
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

            'employee_id'    => 'sometimes|integer|exists:employees,id',
            'start_date'     => 'sometimes|date',
            'end_date'       => 'sometimes|date|after_or_equal:start_date',
            'status'         => 'sometimes|string|in:draft,active,expired,terminated,renewed',
            'amount'         => 'sometimes|numeric|min:0',
            'discount'       => 'nullable|numeric|min:0',
            'notes'          => 'nullable|string',
            'signed_by'      => 'nullable|integer|exists:users,id',
            'payment_method' => 'nullable|string|max:50',

            // Services
            'services'                         => 'nullable|array',
            'services.*.slug'                    => 'sometimes|string|exists:services,slug',
            'services.*.unit_price'            => 'sometimes|numeric|min:0',

            // Layout
            'services.*.layout'                => 'nullable|array',
            'services.*.layout.id'             => 'required_with:services.*.layout|integer|exists:layouts,id',

            // Answers
            'services.*.layout.fields'                         => 'nullable|array',
            'services.*.layout.fields.*.layout_field_id'       => 'sometimes|integer|exists:layout_fields,id',
            'services.*.layout.fields.*.answer'                => 'nullable',
        ];
    }
}
