<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LayoutField extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'layout_id',
        'field_name',
        'is_required',
        'sort_order',
        'default_value',
        'validation_rules',
        'options',
        'placeholder',
        'help_text',
        'field_type',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class, 'layout_id');
    }

    public function layoutAnswers(): HasMany
    {
        return $this->hasMany(LayoutAnswer::class, 'layout_field_id');
    }
}
