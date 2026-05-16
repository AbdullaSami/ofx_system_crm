<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clients extends Model
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
        return $this->belongsTo(Leads::class, 'lead_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employees::class, 'assigned_to');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contracts::class, 'client_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collections::class, 'client_id');
    }
}
