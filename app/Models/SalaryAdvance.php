<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAdvance extends Model
{
    protected $fillable = [
        'employee_id',
        'amount',
        'date',
        'is_settled',
        'settled_date',
        'description',
        'payment_method',
        'treasury_transaction_id',
    ];

    public function treasuryTransaction()
    {
        return $this->belongsTo(TreasuryTransaction::class, 'treasury_transaction_id');
    }
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
