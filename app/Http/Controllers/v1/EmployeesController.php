<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeesController extends Controller
{
    public function index()
    {
        $employees = Employee::with(['salaries', 'roles'])->get();
        return response()->json($employees);
    }

    public function show($id)
    {
        $employee = Employee::with(['salaries', 'roles'])->findOrFail($id);
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }
        return response()->json($employee);
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
            'status' => 'required|in:active,inactive',
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
        $employee = Employee::create([
            'employee_name' => $validatedData['employee_name'],
            'phone' => $validatedData['phone'] ?? null,
            'employee_code' => $validatedData['employee_code'] ?? null,
            'address' => $validatedData['address'] ?? null,
            'email' => $validatedData['email'],
            'whatsapp' => $validatedData['whatsapp'] ?? null,
            'status' => $validatedData['status'],
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
            $employee->salaries()->create([
                'amount' => $validatedData['salary'],
                'effective_date' => now(),
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



        return response()->json($employee, 201);
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
            'status'        => 'sometimes|in:active,inactive',
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

        $employee->update([
            'employee_name' => $validatedData['employee_name'] ?? $employee->employee_name,
            'phone'         => $validatedData['phone'] ?? $employee->phone,
            'employee_code' => $validatedData['employee_code'] ?? $employee->employee_code,
            'address'       => $validatedData['address'] ?? $employee->address,
            'email'         => $validatedData['email'] ?? $employee->email,
            'whatsapp'      => $validatedData['whatsapp'] ?? $employee->whatsapp,
            'status'        => $validatedData['status'] ?? $employee->status,
        ]);

        // Update or create linked user
        if ($validatedData['is_user'] ?? false) {
            $user = $employee->user()->updateOrCreate(
                ['email' => $employee->email],
                [
                    'name'     => $employee->employee_name,
                    'email'    => $validatedData['email'] ?? $employee->email,
                    'password' => isset($validatedData['password'])
                        ? bcrypt($validatedData['password'])
                        : $employee->user->password,
                ]
            );
            if (isset($validatedData['role'])) {
                $user->syncRoles([$validatedData['role']]);
            }
        }

        // Sync role

        // Add new salary record if salary changed
        if (isset($validatedData['salary'])) {
            $employee->salaries()->create([
                'amount'         => $validatedData['salary'],
                'effective_date' => now(),
            ]);
        }

        // Sync team
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

        return response()->json($employee->fresh(), 200);
    }


    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        // Delete related user account
        if ($employee->user) {
            $employee->user()->delete();
        }

        // Revoke all roles
        $employee->roles()->detach();

        // Detach from all teams
        $employee->teams()->detach();

        // Delete salaries
        $employee->salaries()->delete();

        // Delete commissions
        $employee->commissions()->delete();

        $employee->delete();

        return response()->json(['message' => 'Employee deleted successfully'], 200);
    }
}
