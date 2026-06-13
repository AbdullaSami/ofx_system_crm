<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryAccount extends Model
{
    protected $fillable = [
        'account_name',
        'balance',
    ];

    public function transactions()
    {
        return $this->hasMany(TreasuryTransaction::class);
    }
}
