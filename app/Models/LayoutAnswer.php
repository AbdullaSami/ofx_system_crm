<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LayoutAnswer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'layout_field_id',
        'contract_id',
        'answer',
        'answered_by',
        'answered_at',
        'validation_status',
    ];

    public function layoutField(): BelongsTo
    {
        return $this->belongsTo(LayoutField::class, 'layout_field_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}
