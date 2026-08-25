<?php

namespace App\Enums;

enum StudentSubjectEnrollmentStatus: string
{
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'Pending Verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    public function badgeStatus(): string
    {
        return match ($this) {
            self::PendingVerification => 'pending',
            self::Verified => 'approved',
            self::Rejected => 'rejected',
        };
    }
}
