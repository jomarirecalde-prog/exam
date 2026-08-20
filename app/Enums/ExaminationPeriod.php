<?php

namespace App\Enums;

enum ExaminationPeriod: string
{
    case Prelim = 'PRELIM';
    case Midterm = 'MIDTERM';
    case Final = 'FINAL';

    public function label(): string
    {
        return match ($this) {
            self::Prelim => 'Prelim',
            self::Midterm => 'Midterm',
            self::Final => 'Final',
        };
    }
}
