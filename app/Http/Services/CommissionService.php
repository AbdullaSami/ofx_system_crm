<?php

namespace App\Http\Services;

use App\Models\Commission;
use App\Models\Employee;


class CommissionService
{
    public function addCommission($contractId, $contractAmount, $employeeId, $commissionRate = null, $totalCommission = null)
    {
        $employee = Employee::findOrFail($employeeId);

        $employee->commission()->create([
            'contract_id' => $contractId,
            'total_contracts_value' => $contractAmount,
            'commission_rate' => $commissionRate,
            'total_commission' => $totalCommission,
            'effective_date' => now(),
            'status' => 'paid'
        ]);

        return;
    }

    public function updateCommission($contractId, $newContractAmount, $employeeId)
    {
        $commission = Commission::where('employee_id', $employeeId)->where('contract_id', $contractId);
        $commission->update([
            'total_contracts_value' => $newContractAmount,
            'effective_date' => now(),
            'status' => 'paid'
        ]);

        return $commission->fresh();
    }
}
