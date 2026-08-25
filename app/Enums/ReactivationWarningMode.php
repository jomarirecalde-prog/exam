<?php

namespace App\Enums;

enum ReactivationWarningMode: string
{
    case Reset = 'reset';
    case Keep = 'keep';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Reset => 'Reset warnings to 0',
            self::Keep => 'Keep existing warnings',
            self::Manual => 'Set warning count manually',
        };
    }
}
