<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryTransaction extends Model
{
    protected $fillable = [
        'treasury_account_id',
        'transaction_type',
        'amount',
        'description',
    ];

    public function salaryAvance()
    {
        return $this->hasOne(SalaryAdvance::class, 'treasury_transaction_id');
    }

    public function treasuryAccount()
    {
        return $this->belongsTo(TreasuryAccount::class);
    }
}
