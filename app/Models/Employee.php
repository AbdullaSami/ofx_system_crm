<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
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
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'assigned_to');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'employee_id');
    }

    public function teamsAsLead(): HasMany
    {
        return $this->hasMany(Team::class, 'lead_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_employee', 'employee_id', 'team_id')
            ->withPivot('role', 'assigned_at', 'joined_at', 'left_at');
    }

    public function salary(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class, 'employee_id');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Salary::class);
    }

    public function commission(): HasMany
    {
        return $this->hasMany(Commission::class, 'employee_id');
    }
    public function commissions(): HasMany
    {
        return $this->hasMany(EmployeeCommission::class, 'employee_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'employee_id');
    }

 
}
