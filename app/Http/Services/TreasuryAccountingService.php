<?php

namespace App\Http\Services;

use App\Models\TreasuryAccount;
use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\DB;


class TreasuryAccountingService
{
// ============================================
// TreasuryAccountingService.php
// ============================================

    /**
     * Record a treasury transaction and update account balance.
     * Now returns the created transaction (needed by controller to link back)
     * and locks the account row inside the transaction to avoid double-spend race.
     */
    public function recordTransaction($treasuryAccountId, $amount, $type, $description = null)
    {
        return DB::transaction(function () use ($treasuryAccountId, $amount, $type, $description) {

            // lock row here too — this service can be called outside an already-locked context
            $treasuryAccount = TreasuryAccount::where('id', $treasuryAccountId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($type === 'debit' && $treasuryAccount->balance < $amount) {
                throw new \RuntimeException('Insufficient balance in treasury account');
            }

            $transaction = TreasuryTransaction::create([
                'treasury_account_id' => $treasuryAccountId,
                'amount'              => $amount,
                'transaction_type'    => $type,
                'description'         => $description,
            ]);

            if ($type === 'credit') {
                $treasuryAccount->balance += $amount;
            } elseif ($type === 'debit') {
                $treasuryAccount->balance -= $amount;
            }

            $treasuryAccount->save();

            return $transaction; // controller needs this id
        });
    }
}
