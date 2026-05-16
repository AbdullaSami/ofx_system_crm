<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Departments extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'description',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Services::class, 'department_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Teams::class, 'team_dep_service', 'department_id', 'team_id')
            ->withPivot('service_id')
            ->withTimestamps();
    }

    public function servicesViaTeams(): BelongsToMany
    {
        return $this->belongsToMany(Services::class, 'team_dep_service', 'department_id', 'service_id')
            ->withPivot('team_id')
            ->withTimestamps();
    }
}
