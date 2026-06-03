<?php

namespace App\Models;

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
    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];
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
            ->withPivot('quantity', 'unit_price', 'discount', 'billing_frequency', 'status')
            ->withTimestamps();
    }

    public function layoutAnswers(): HasMany
    {
        return $this->hasMany(LayoutAnswer::class, 'contract_id');
    }
}
