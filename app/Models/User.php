<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'password',
        'password_login_enabled',
        'is_active',
        'two_factor_enabled',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'offline_app_pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_login_enabled' => 'boolean',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function instructor(): HasOne
    {
        return $this->hasOne(Instructor::class);
    }

    public function linkedAccounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class);
    }

    public function googleClassroomConnection(): HasOne
    {
        return $this->hasOne(GoogleClassroomConnection::class);
    }

    public function googleClassroomCourseLinks(): HasMany
    {
        return $this->hasMany(GoogleClassroomCourseLink::class);
    }

    public function hasLinkedProvider(string $provider): bool
    {
        return $this->linkedAccounts()->where('provider', $provider)->exists();
    }

    public function hasPassword(): bool
    {
        return (bool) ($this->password_login_enabled ?? true);
    }

    public function fullName(): string
    {
        return trim(collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '))
            ?: $this->name;
    }

    public function firstName(): string
    {
        return $this->first_name ?: explode(' ', (string) $this->name)[0];
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
