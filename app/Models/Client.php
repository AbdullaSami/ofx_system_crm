<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'whatsapp',
        'company',
        'status',
        'lead_id',
        'assigned_to',
        'user_id',
        'address',
        'city',
        'country',
        'tax_id',
        'registration_number',
        'payment_terms',
        'credit_limit',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'client_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'client_id');
    }

    /**
     * Scope a query to only include records visible to the given user.
     *
     * clients.view      → all clients
     * clients.view.own  → only clients assigned to the user's employee
     */
    public function scopeVisibleTo(Builder $query, \App\Models\User $user): Builder
    {
        if ($user->can('clients.view')) {
            return $query;
        }

        if ($user->can('clients.view.own')) {
            $employeeId = $user->getEmployeeId();
            if (! $employeeId) {
                return $query->whereRaw('1 = 0');
            }
            return $query->where('assigned_to', $employeeId);
        }

        abort(403, 'You do not have permission to view clients.');
    }
}
