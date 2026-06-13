<?php

namespace App\Http\Services;

use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\DB;


class TreasuryAccountingService
{
    public function recordTransaction($treasuryAccountId, $amount, $type, $description = null)
    {
        DB::transaction(function () use ($treasuryAccountId, $amount, $type, $description) {
            // Create the transaction record
            TreasuryTransaction::create([
                'treasury_account_id' => $treasuryAccountId,
                'amount' => $amount,
                'type' => $type,
                'description' => $description,
            ]);

            // Update the treasury account balance
            $treasuryAccount = TreasuryAccount::findOrFail($treasuryAccountId);
            if ($type === 'credit') {
                $treasuryAccount->balance += $amount;
            } elseif ($type === 'debit') {
                $treasuryAccount->balance -= $amount;
            }
            $treasuryAccount->save();
        });
    }

}
