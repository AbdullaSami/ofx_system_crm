<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contracts extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'employee_id',
        'contract_number',
        'start_date',
        'end_date',
        'amount',
        'notes',
        'discount',
        'status',
        'signed_by',
        'document_path',
        'billing_cycle',
        'currency',
        'tax_rate',
        'payment_method',
        'renewal_date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Clients::class, 'client_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employees::class, 'employee_id');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collections::class, 'contract_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Services::class, 'contract_service', 'contract_id', 'service_id')
            ->withPivot('quantity', 'unit_price', 'discount', 'billing_frequency', 'status')
            ->withTimestamps();
    }

    public function layoutAnswers(): HasMany
    {
        return $this->hasMany(LayoutAnswer::class, 'contract_id');
    }
}
