<?php

namespace App\Enums;

enum AuthProvider: string
{
    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
        };
    }
}
