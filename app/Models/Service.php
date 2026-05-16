<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Service extends Model
{
    use HasSlug, SoftDeletes;

    protected $fillable = [
        'department_id',
        'name',
        'slug',
        'is_active',
        'price',
        'cost',
        'description',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(Layout::class, 'service_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_dep_service', 'service_id', 'team_id')
            ->withPivot('department_id')
            ->withTimestamps();
    }

    public function departmentsViaTeams(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'team_dep_service', 'service_id', 'department_id')
            ->withPivot('team_id')
            ->withTimestamps();
    }

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'contract_service', 'service_id', 'contract_id')
            ->withPivot('quantity', 'unit_price', 'discount', 'billing_frequency', 'status')
            ->withTimestamps();
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'lead_service', 'service_id', 'lead_id')
            ->withTimestamps();
    }
}
