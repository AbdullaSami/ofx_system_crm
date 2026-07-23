<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use HasFactory;

    public const TYPE_WAGE = 'wage';
    public const TYPE_REFUND = 'refund';
    public const TYPE_GENERAL = 'general';
    public const TYPE_PAY_BILL = 'pay_bill';

    protected $fillable = [
        'treasury_id',
        'expense_type',
        'expensable_type',
        'expensable_id',
        'amount',
        'expense_date',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class);
    }

    public function expensable(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include records visible to the given user.
     *
     * expenses.view      → all expenses
     * expenses.view.own  → only expenses created by the authenticated user
     */
    public function scopeVisibleTo(Builder $query, \App\Models\User $user): Builder
    {
        if ($user->can('expenses.view')) {
            return $query;
        }

        if ($user->can('expenses.view.own')) {
            return $query->where('created_by', $user->id);
        }

        abort(403, 'You do not have permission to view expenses.');
    }
}
