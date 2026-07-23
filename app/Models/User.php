<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'user_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'signed_by');
    }

    public function layoutAnswers(): HasMany
    {
        return $this->hasMany(LayoutAnswer::class, 'answered_by');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function personalAccessTokens(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    /**
     * Get the associated Employee ID for this user.
     * Auto-links an unlinked Employee record matching the user's email if available.
     */
    public function getEmployeeId(): ?int
    {
        if ($this->employee) {
            return $this->employee->id;
        }

        $employee = Employee::where('email', $this->email)->first();
        if ($employee) {
            if (is_null($employee->user_id)) {
                $employee->update(['user_id' => $this->id]);
                $this->unsetRelation('employee');
            }
            return $employee->id;
        }

        return null;
    }
}
