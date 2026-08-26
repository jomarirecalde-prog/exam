<?php

namespace App\Models;

use App\Enums\AuthProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkedAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_account_id',
        'provider_email',
        'provider_name',
        'provider_avatar',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
            'provider' => AuthProvider::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
