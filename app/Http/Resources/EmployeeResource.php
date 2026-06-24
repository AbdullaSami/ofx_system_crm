<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_name' => $this->employee_name,
            'phone' => $this->phone,
            'employee_code' => $this->employee_code,
            'address' => $this->address,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'status' => $this->status,
            'role' => $this->position,
            'salary' => $this->salary ? $this->salary->map(function ($salary) {
                return [
                    'amount' => $salary->amount,
                    'currency' => $salary->currency
                ];
            }) : null,
            'salaries' => $this->salaries ? $this->salaries->map(function ($salary) {
                return [
                    'amount' => $salary->amount,
                    'currency' => $salary->currency,
                    'effective_date' => $salary->effective_date,
                    'status' => $salary->status,
                    'is_refund' => $salary->is_refund ?? null,
                    'refund_date' => $salary->refund_date ?? null,
                    'refund_amount' => $salary->refund_amount ?? null,
                ];
            }) : null,
            'sales' => $this->contracts ? $this->contracts->map(function ($contract) {
                return [
                    'id' => $contract->id,
                    'client_name' => $contract->client ? $contract->client->client_name : null,
                    'value' => $contract->amount,
                    'is_terminated' => $contract->is_terminated,
                    'terminated_date' => $contract->terminated_date,
                    'is_refund' => $contract->is_refund,
                    'refund_date' => $contract->refund_date,
                    'refund_amount' => $contract->refund_amount,
                    'status' => $contract->status,
                ];
            }) : null,
            'commission' => $this->commission ? $this->commission->map(function ($commission) {
                return [
                    'total_contracts_value' => $commission->total_contracts_value,
                    'commission_rate' => $commission->commission_rate,
                    'total_commission' => $commission->total_commission,
                    'effective_date' => $commission->effective_date,
                    'status' => $commission->status,
                ];
            }) : null,

            'total_commission_value' => (function () {
                // Step 1: Calculate effective sales value from contracts
                $effectiveSalesValue = $this->contracts
                    ? $this->contracts->sum(function ($contract) {
                        $refund = ($contract->is_refund && $contract->refund_amount)
                            ? $contract->refund_amount
                            : 0;
                        if ($contract->is_terminated) {
                            // Terminated: use amount_paid minus refund (if any)
                            $paid = $contract->amount_paid ?? 0;
                            return $paid - $refund;
                        }

                        // Not terminated: use full contract amount
                        return $contract->amount - $refund ?? 0;
                    })
                    : 0;

                // Step 2: Find the matching commission tier based on effective value
                $matchedCommission = $this->commissions
                    ?->filter(fn($c) => $effectiveSalesValue >= $c->amount)
                    ->sortByDesc('amount')
                    ->first();

                $commissionRate = $matchedCommission?->commission_rate ?? 0;

                // Step 3: Apply rate to effective sales value
                return $effectiveSalesValue * ($commissionRate / 100);
            })(),

            'commissions' => $this->commissions ? $this->commissions->map(function ($commission) {
                return [
                    'amount' => $commission->amount,
                    'commission_rate' => $commission->commission_rate,
                ];
            }) : null,
            'team' => $this->teams ? $this->teams->map(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                ];
            }) : null,
            'is_user' => $this->user_id ? $this->user : false,
            'created_at' => $this->created_at,
        ];
    }
}
