<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'employee_id',
        'contract_number',
        'start_date',
        'end_date',
        'amount',
        'amount_paid',
        'notes',
        'discount',
        'status',
        'is_terminated',
        'terminated_date',
        'is_refund',
        'refund_date',
        'refund_amount',
        'signed_by',
        'document_path',
        'billing_cycle',
        'currency',
        'tax_rate',
        'payment_method',
        'renewal_date',
    ];
    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'terminated_date'   => 'date',

    ];

    public function commission(){
        return $this->belongsTo(Commission::class);
    }
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'contract_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'contract_service', 'contract_id', 'service_id')
            ->withPivot('quantity', 'unit_price', 'discount', 'billing_frequency', 'status', 'is_cancelled', 'cancelled_date', 'is_refund', 'refund_date', 'refund_amount')
            ->withTimestamps();
    }

    public function layoutAnswers(): HasMany
    {
        return $this->hasMany(LayoutAnswer::class, 'contract_id');
    }

    /**
     * Scope a query to only include records visible to the given user.
     *
     * contracts.view      → all contracts
     * contracts.view.own  → only contracts belonging to the user's employee
     */
    public function scopeVisibleTo(Builder $query, \App\Models\User $user): Builder
    {
        if ($user->can('contracts.view')) {
            return $query;
        }

        if ($user->can('contracts.view.own')) {
            $employeeId = $user->employee?->id;
            abort_if(
                ! $employeeId,
                403,
                'Your account has no linked employee record. Contact an administrator.'
            );
            return $query->where('employee_id', $employeeId);
        }

        abort(403, 'You do not have permission to view contracts.');
    }
}
