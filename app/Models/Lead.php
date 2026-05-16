<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lead_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'whatsapp',
        'company',
        'source',
        'status',
        'assigned_to',
        'estimated_value',
        'follow_up_date',
        'converted_at',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'lead_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'lead_service', 'lead_id', 'service_id')
            ->withTimestamps();
    }
}
