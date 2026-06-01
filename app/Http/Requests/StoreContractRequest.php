<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create contracts', Contract::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Client
            'client_id'             => 'nullable|integer|exists:clients,id',
            'client_name'           => 'required_without:client_id|string|max:255',
            'first_name'            => 'required_without:client_id|string|max:255',
            'last_name'             => 'required_without:client_id|string|max:255',
            'email'                 => 'required_without:client_id|email|max:255',
            'phone'                 => 'required_without:client_id|string|max:20',
            'company'               => 'nullable|string|max:255',
            'whatsapp'              => 'nullable|string|max:20',
            'lead_id'               => 'nullable|integer|exists:leads,id',
            'assigned_to'           => 'nullable|integer|exists:users,id',
            'user_id'               => 'nullable|integer|exists:users,id',

            // Contract
            'employee_id'           => 'required|integer|exists:employees,id',
            'start_date'            => 'required|date',
            'end_date'              => 'required|date|after_or_equal:start_date',
            'status'                => 'required|string|in:draft,active,expired,terminated,renewed',
            'amount'                => 'required|numeric|min:0',
            'notes'                 => 'nullable|string',
            'discount'              => 'nullable|numeric|min:0|lte:amount',  // discount can't exceed amount
            'signed_by'             => 'nullable|integer|exists:users,id',
            'payment_method'        => 'nullable|string|max:50',

            // Services
            'services'              => 'nullable|array',
            'services.*.id'         => 'required|integer|exists:services,id',
            'services.*.unit_price' => 'required|numeric|min:0',
        ];
    }
}
