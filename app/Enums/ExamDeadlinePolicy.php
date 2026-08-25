<?php

namespace App\Enums;

enum ExamDeadlinePolicy: string
{
    case StopAll = 'stop_all';
    case AllowActiveFinish = 'allow_active_finish';

    public function label(): string
    {
        return match ($this) {
            self::StopAll => 'Automatically End and Submit Active Examinations',
            self::AllowActiveFinish => 'Allow Active Students to Complete Their Remaining Time',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::StopAll => 'When the deadline is reached, all active attempts are ended immediately and submitted using their latest saved answers.',
            self::AllowActiveFinish => 'When the deadline is reached, new students cannot start, but students already taking the exam may finish their individual time.',
        };
    }
}
