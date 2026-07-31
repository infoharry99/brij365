<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'company_id',
        'name',
        'email',
        'password',
        'status',
        'profile_photo_path',
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'portal_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'recipient_user_id');
    }

    public function projectTeamAssignments(): HasMany
    {
        return $this->hasMany(ProjectTeamAssignment::class);
    }

    public function triggeredNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'triggered_by_user_id');
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->role?->permissions ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function isDirector(): bool
    {
        return $this->hasPermission('*')
            || in_array($this->role?->slug, ['director', 'system_admin'], true)
            || str_contains(strtolower((string) $this->role?->name), 'director');
    }
}
