<?php

namespace App\Http\Controllers\v1;

use App\Http\Services\TreasuryAccountingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Models\TreasuryAccount;
use Illuminate\Support\Facades\DB;
class SalaryAdvanceController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $salaryAdvances = Employee::with('salaryAdvances')->get();
            return response()->json($salaryAdvances);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch salary advances', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $salaryAdvance = Employee::with('salaryAdvances')->findOrFail($id);
            return response()->json($salaryAdvance);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch salary advance', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created salary advance + treasury deduction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'amount'         => 'required|numeric|min:0.01',
            'date'           => 'required|date',
            'is_settled'     => 'boolean',
            'settled_date'   => 'nullable|date',
            'payment_method' => 'required|string',
        ]);

        try {
            // wrap everything in one transaction — treasury debit + advance record must succeed/fail together
            $salaryAdvance = DB::transaction(function () use ($validated) {

                // lock the row INSIDE the transaction, not after fetching — prevents race condition
                $treasuryAccount = TreasuryAccount::where('account_name', $validated['payment_method'])
                    ->lockForUpdate()
                    ->first();

                if (!$treasuryAccount) {
                    throw new \RuntimeException('Treasury account not found');
                }

                if ($treasuryAccount->balance < $validated['amount']) {
                    throw new \RuntimeException('Insufficient balance in treasury account');
                }

                // debit treasury (service handles ledger + balance update)
                $treasuryService = new TreasuryAccountingService();
                $treasuryTransaction = $treasuryService->recordTransaction(
                    $treasuryAccount->id,
                    $validated['amount'],
                    'debit',
                    'Salary Advance Payment'
                );

                // create the advance, store link back to the treasury transaction for reversal later
                $employee = Employee::findOrFail($validated['employee_id']);

                return $employee->salaryAdvances()->create([
                    'amount'                => $validated['amount'],
                    'date'                  => $validated['date'],
                    'is_settled'            => $validated['is_settled'] ?? false,
                    'settled_date'          => $validated['settled_date'] ?? null,
                    'payment_method'        => $validated['payment_method'],
                    'treasury_transaction_id' => $treasuryTransaction->id, // needs column added, see note below
                ]);
            });

            return response()->json($salaryAdvance, 201);
        } catch (\RuntimeException $e) {
            // expected business errors (no account / insufficient balance)
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create salary advance', 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Update salary advance — handles amount change and/or payment_method change
     * by reversing the old treasury effect and applying the new one.
     */
    public function update(Request $request, SalaryAdvance $salaryAdvance)
    {
        $validated = $request->validate([
            'employee_id'    => 'sometimes|required|exists:employees,id',
            'amount'         => 'sometimes|required|numeric|min:0.01',
            'date'           => 'sometimes|required|date',
            'is_settled'     => 'boolean',
            'settled_date'   => 'nullable|date',
            'payment_method' => 'sometimes|required|string',
        ]);

        try {
            $updated = DB::transaction(function () use ($validated, $salaryAdvance) {

                $treasuryService = new TreasuryAccountingService();

                $amountChanged = isset($validated['amount']) && bccomp($validated['amount'], $salaryAdvance->amount, 2) !== 0;
                $methodChanged = isset($validated['payment_method']) && $validated['payment_method'] !== $salaryAdvance->payment_method;

                // only touch treasury if amount or account actually changed
                if ($amountChanged || $methodChanged) {

                    // 1. reverse the OLD treasury effect (credit back what was debited)
                    $oldAccount = TreasuryAccount::where('account_name', $salaryAdvance->payment_method)
                        ->lockForUpdate()
                        ->first();

                    if (!$oldAccount) {
                        throw new \RuntimeException('Original treasury account not found, cannot reverse');
                    }

                    $treasuryService->recordTransaction(
                        $oldAccount->id,
                        $salaryAdvance->amount,
                        'credit',
                        'Reversal - Salary Advance Update'
                    );

                    // 2. apply the NEW treasury effect on the (possibly new) account
                    $newMethod = $validated['payment_method'] ?? $salaryAdvance->payment_method;
                    $newAmount = $validated['amount'] ?? $salaryAdvance->amount;

                    $newAccount = TreasuryAccount::where('account_name', $newMethod)
                        ->lockForUpdate()
                        ->first();

                    if (!$newAccount) {
                        throw new \RuntimeException('New treasury account not found');
                    }

                    if ($newAccount->balance < $newAmount) {
                        throw new \RuntimeException('Insufficient balance in treasury account');
                    }

                    $newTransaction = $treasuryService->recordTransaction(
                        $newAccount->id,
                        $newAmount,
                        'debit',
                        'Salary Advance Payment (Updated)'
                    );

                    $validated['treasury_transaction_id'] = $newTransaction->id;
                }

                $salaryAdvance->update($validated);

                return $salaryAdvance->fresh();
            });

            return response()->json($updated);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update salary advance', 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Delete salary advance — reverses treasury debit (credit back) before deleting the record.
     */
    public function destroy(SalaryAdvance $salaryAdvance)
    {
        try {
            DB::transaction(function () use ($salaryAdvance) {

                $treasuryAccount = TreasuryAccount::where('account_name', $salaryAdvance->payment_method)
                    ->lockForUpdate()
                    ->first();

                if (!$treasuryAccount) {
                    throw new \RuntimeException('Treasury account not found, cannot reverse deduction');
                }

                // credit back the amount that was originally deducted
                $treasuryService = new TreasuryAccountingService();
                $treasuryService->recordTransaction(
                    $treasuryAccount->id,
                    $salaryAdvance->amount,
                    'credit',
                    'Reversal - Salary Advance Deleted'
                );

                $salaryAdvance->delete();
            });

            return response()->json(['message' => 'Salary advance deleted and treasury reversed'], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete salary advance', 'message' => $e->getMessage()], 500);
        }
    }
}
