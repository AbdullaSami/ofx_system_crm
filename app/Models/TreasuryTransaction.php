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

    public function treasuryAccount()
    {
        return $this->belongsTo(TreasuryAccount::class);
    }
}
