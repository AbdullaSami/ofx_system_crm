<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Department extends Model
{
    use HasSlug, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'description',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'department_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_dep_service', 'department_id', 'team_id')
            ->withPivot('service_id')
            ->withTimestamps();
    }

    public function servicesViaTeams(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'team_dep_service', 'department_id', 'service_id')
            ->withPivot('team_id')
            ->withTimestamps();
    }
}
