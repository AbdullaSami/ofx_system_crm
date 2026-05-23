<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUp extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lead_id',
        'employee_id',
        'type',
        'status',
        'notes',
        'follow_up_date',
        'next_follow_up_date',
    ];

    /**
     * Get the lead associated with the follow-up.
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the employee associated with the follow-up.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
