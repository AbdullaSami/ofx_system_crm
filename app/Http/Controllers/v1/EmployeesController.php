<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\EmployeeResource;

class EmployeesController extends Controller
{
    public function index()
    {
        $employees = Employee::with(['salary', 'salaries', 'commissions', 'commission', 'contracts'])->get();

        return response()->json(EmployeeResource::collection($employees));
    }

    public function show($id)
    {
        $employee = Employee::with(['salary', 'salaries', 'commissions', 'commission', 'contracts'])->findOrFail($id);
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }
        return response()->json(new EmployeeResource($employee));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'employee_code' => 'nullable|string|max:50|unique:employees,employee_code',
            'address' => 'nullable|string|max:500',
            'email' => 'required|email|unique:employees,email',
            'whatsapp' => 'nullable|string|max:20',
            'salary' => 'nullable|numeric',
            'target' => 'nullable|numeric',
            'team_id' => 'nullable|exists:teams,id',
            'role' => 'nullable|exists:roles,name',
            'is_user' => 'nullable|boolean',
            'password' => 'nullable|string|min:6',
            'commissions' => 'nullable|array',
            'commissions.*.amount' => 'required_with:commissions|numeric',
            'commissions.*.commission_rate' => 'required_with:commissions|numeric',

        ]);
        DB::beginTransaction();
        try {
            $employee = Employee::create([
                'employee_name' => $validatedData['employee_name'],
                'phone' => $validatedData['phone'] ?? null,
                'employee_code' => $validatedData['employee_code'] ?? null,
                'address' => $validatedData['address'] ?? null,
                'email' => $validatedData['email'],
                'whatsapp' => $validatedData['whatsapp'] ?? null,
                'status' => 'active',
                'position' => $validatedData['role'] ?? null,
            ]);

            if ($validatedData['is_user'] ?? false) {
                $user = $employee->user()->create([
                    'name' => $employee->employee_name,
                    'email' => $employee->email,
                    'password' => bcrypt($validatedData['password'] ?? 'defaultpassword'), // You should handle password properly
                ]);
                if (isset($validatedData['role'])) {
                    $user->assignRole($validatedData['role']);
                }
            }


            if (isset($validatedData['salary'])) {
                $employee->salary()->create([
                    'amount' => $validatedData['salary'],
                    'currency' => $validatedData['currency'] ?? 'EGP',
                ]);
            }

            if (isset($validatedData['team_id'])) {
                $employee->teams()->attach($validatedData['team_id'], ['role' => $validatedData['role'] ?? 'Member', 'assigned_at' => now()]);
            }

            if (isset($validatedData['commissions'])) {
                foreach ($validatedData['commissions'] as $commission) {
                    $employee->commissions()->create([
                        'amount' => $commission['amount'],
                        'commission_rate' => $commission['commission_rate'],
                    ]);
                }
            }
            DB::commit();
            return response()->json($employee, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create employee', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validatedData = $request->validate([
            'employee_name' => 'sometimes|string|max:255',
            'phone'         => 'nullable|string|max:20',
            'employee_code' => 'nullable|string|max:50|unique:employees,employee_code,' . $id,
            'address'       => 'nullable|string|max:500',
            'email'         => 'sometimes|email|unique:employees,email,' . $id,
            'whatsapp'      => 'nullable|string|max:20',
            'salary'        => 'nullable|numeric',
            'target'        => 'nullable|numeric',
            'team_id'       => 'nullable|exists:teams,id',
            'role'          => 'nullable|exists:roles,name',
            'is_user'       => 'nullable|boolean',
            'password'      => 'nullable|string|min:6',
            'commissions'   => 'nullable|array',
            'commissions.*.amount'          => 'required_with:commissions|numeric',
            'commissions.*.commission_rate' => 'required_with:commissions|numeric',
        ]);

        DB::beginTransaction();
        try {
            // Update core employee fields
            $employee->update([
                'employee_name' => $validatedData['employee_name'] ?? $employee->employee_name,
                'phone'         => array_key_exists('phone', $validatedData) ? $validatedData['phone'] : $employee->phone,
                'employee_code' => array_key_exists('employee_code', $validatedData) ? $validatedData['employee_code'] : $employee->employee_code,
                'address'       => array_key_exists('address', $validatedData) ? $validatedData['address'] : $employee->address,
                'email'         => $validatedData['email'] ?? $employee->email,
                'whatsapp'      => array_key_exists('whatsapp', $validatedData) ? $validatedData['whatsapp'] : $employee->whatsapp,
            ]);

            // Handle user account
            if ($validatedData['is_user'] ?? false) {
                $user = $employee->user()->updateOrCreate(
                    ['email' => $employee->email],
                    [
                        'name'     => $employee->employee_name,
                        'email'    => $employee->email,
                        'password' => isset($validatedData['password'])
                            ? bcrypt($validatedData['password'])
                            : ($employee->user?->password ?? bcrypt('defaultpassword')),
                    ]
                );

                if (isset($validatedData['role'])) {
                    $user->syncRoles([$validatedData['role']]);
                }
            }

            // Add new salary record if provided
            if (isset($validatedData['salary'])) {
                $employee->salary()->create([
                    'amount'         => $validatedData['salary'],
                    'currency'       => $validatedData['currency'] ?? 'EGP',
                ]);
            }

            // Sync team membership
            if (isset($validatedData['team_id'])) {
                $employee->teams()->syncWithoutDetaching([
                    $validatedData['team_id'] => [
                        'role'        => $validatedData['role'] ?? 'Member',
                        'assigned_at' => now(),
                    ],
                ]);
            }

            // Replace commissions if provided
            if (isset($validatedData['commissions'])) {
                $employee->commissions()->delete();
                foreach ($validatedData['commissions'] as $commission) {
                    $employee->commissions()->create([
                        'amount'          => $commission['amount'],
                        'commission_rate' => $commission['commission_rate'],
                    ]);
                }
            }

            DB::commit();
            return response()->json($employee->fresh(), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update employee', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        // Delete related user account
        if ($employee->user) {
            $employee->user()->delete();
        }

        // Detach from all teams
        $employee->teams()->detach();

        // Delete salaries
        $employee->salary()->delete();

        // Delete commissions
        $employee->commissions()->delete();

        $employee->delete();

        return response()->json(['message' => 'Employee deleted successfully'], 200);
    }

    public function paySalary(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validatedData = $request->validate([
            'amount' => 'required|numeric',
            'bonus' => 'nullable|numeric',
            'deductions' => 'nullable|numeric',
            'payment_method' => 'nullable|string|max:50',
            'status' => 'nullable|in:pending,approved,paid',
        ]);

        $employee->salaries()->create([
            'amount' => $validatedData['amount'],
            'currency' => 'EGP',
            'bonus' => $validatedData['bonus'] ?? 0,
            'deductions' => $validatedData['deductions'] ?? 0,
            'payment_method' => $validatedData['payment_method'] ?? null,
            'effective_date' => now(),
            'status' => $validatedData['status'] ?? 'paid',
        ]);

        return response()->json(['message' => 'Salary paid successfully'], 200);
    }

    public function payCommission(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        $contractsFrom = $request->input('contracts_from');
        $contractsTo = $request->input('contracts_to');

        $contractsQuery = $employee->contracts()
            ->whereBetween('created_at', [$contractsFrom, $contractsTo])
        ->where('status', 'approved');

        $totalContractsValue = $contractsQuery->sum('amount');
        $commissionRate = EmployeeCommission::where('employee_id', $employee->id)
        ->where('amount', '>=', $totalContractsValue)
        ->latest()
        ->first()?->commission_rate ?? 0;

        if ($commissionRate <= 0) {
            return response()->json(['message' => 'No commission rate found for the total contract value'], 400);
        }
        $totalCommission = ($totalContractsValue * $commissionRate) / 100;

        $employee->commission()->create([
            'total_contracts_value' => $totalContractsValue,
            'commission_rate' => $commissionRate,
            'total_commission' => $totalCommission,
            'effective_date' => now(),
            'status' => 'paid',
        ]);

        return response()->json(['message' => 'Commission paid successfully'], 200);
    }

}
