<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Teams extends Model
{
    use HasSlug, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'description',
        'owner_id',
        'lead_id',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Employees::class, 'lead_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employees::class, 'team_employee', 'team_id', 'employee_id')
            ->withPivot('role', 'assigned_at', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Departments::class, 'team_dep_service', 'team_id', 'department_id')
            ->withPivot('service_id')
            ->withTimestamps();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Services::class, 'team_dep_service', 'team_id', 'service_id')
            ->withPivot('department_id')
            ->withTimestamps();
    }
}
