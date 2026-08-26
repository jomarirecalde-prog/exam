<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleClassroomConnection extends Model
{
    protected $fillable = [
        'user_id',
        'google_account_id',
        'google_email',
        'scopes',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'is_active',
        'connected_at',
        'last_synced_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courseLinks(): HasMany
    {
        return $this->hasMany(GoogleClassroomCourseLink::class, 'user_id', 'user_id');
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
