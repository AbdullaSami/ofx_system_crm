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
                ];
            }) : null,
            'sales' => $this->contracts ? $this->contracts->map(function ($contract) {
                return [
                    'id' => $contract->id,
                    'client_name' => $contract->client ? $contract->client->client_name : null,
                    'value' => $contract->amount,
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
                $totalSum = $this->commission?->sum('total_contracts_value') ?? 0;

                $matchedCommission = $this->commissions
                    ?->filter(fn($c) => $totalSum >= $c->amount)
                    ->sortByDesc('amount')
                    ->first();

                $commissionRate = $matchedCommission?->commission_rate ?? 0;

                return $totalSum * ($commissionRate / 100);
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
