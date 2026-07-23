<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Scope a query to only include records visible to the given user.
     *
     * follow-ups.view      → all follow-ups
     * follow-ups.view.own  → only follow-ups belonging to the user's employee
     */
    public function scopeVisibleTo(Builder $query, \App\Models\User $user): Builder
    {
        if ($user->can('follow-ups.view')) {
            return $query;
        }

        if ($user->can('follow-ups.view.own')) {
            $employeeId = $user->employee?->id;
            abort_if(
                ! $employeeId,
                403,
                'Your account has no linked employee record. Contact an administrator.'
            );
            return $query->where('employee_id', $employeeId);
        }

        abort(403, 'You do not have permission to view follow-ups.');
    }
}
