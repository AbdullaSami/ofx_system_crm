<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSalary extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'employee_id',
        'amount',
        'currency',
    ];
    public $timestamps = false;

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
