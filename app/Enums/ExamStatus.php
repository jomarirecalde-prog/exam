<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Scheduled = 'SCHEDULED';
    case Active = 'ACTIVE';
    case Paused = 'PAUSED';
    case Ended = 'ENDED';
    case Closed = 'CLOSED';
    case Expired = 'EXPIRED';
    case Archived = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Active',
            self::Scheduled => 'Scheduled',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Ended => 'Ended by Instructor',
            self::Closed => 'Deadline Reached',
            self::Expired => 'Expired',
            self::Archived => 'Archived',
        };
    }

    public function isStudentVisible(): bool
    {
        return in_array($this, [
            self::Published,
            self::Active,
            self::Scheduled,
            self::Ended,
            self::Closed,
        ], true);
    }

    public function allowsNewAttempts(): bool
    {
        return in_array($this, [
            self::Published,
            self::Active,
        ], true);
    }
}
