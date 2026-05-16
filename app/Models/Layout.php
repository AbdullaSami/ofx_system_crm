<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Layout extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'label',
        'service_id',
        'is_active',
        'is_default',
        'version',
        'description',
        'sort_order',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    public function layoutFields(): HasMany
    {
        return $this->hasMany(LayoutField::class, 'layout_id');
    }
}
