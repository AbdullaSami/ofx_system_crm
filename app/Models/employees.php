<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employees extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'whatsapp',
        'status',
        'user_id',
        'department_id',
        'hire_date',
        'termination_date',
        'position',
        'employee_code',
        'manager_id',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Departments::class, 'department_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employees::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employees::class, 'manager_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Leads::class, 'assigned_to');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Clients::class, 'assigned_to');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contracts::class, 'employee_id');
    }

    public function teamsAsLead(): HasMany
    {
        return $this->hasMany(Teams::class, 'lead_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Teams::class, 'team_employee', 'employee_id', 'team_id')
            ->withPivot('role', 'assigned_at', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Salary::class, 'employee_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(EmployeeCommissions::class, 'employee_id');
    }
}
