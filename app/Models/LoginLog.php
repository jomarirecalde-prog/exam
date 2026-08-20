<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $fillable = [
        'user_id', 'email', 'ip_address', 'user_agent',
        'successful', 'failure_reason', 'logged_in_at',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'logged_in_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
