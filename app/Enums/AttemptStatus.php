<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case NotStarted = 'NOT_STARTED';
    case InProgress = 'IN_PROGRESS';
    case Submitted = 'SUBMITTED';
    case AutoSubmitted = 'AUTO_SUBMITTED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';
    case SyncPending = 'SYNC_PENDING';
    case Synced = 'SYNCED';
    case LockedViolationLimit = 'LOCKED_VIOLATION_LIMIT';

    public function isActive(): bool
    {
        return $this === self::InProgress;
    }

    public function isLocked(): bool
    {
        return $this === self::LockedViolationLimit;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Submitted,
            self::AutoSubmitted,
            self::Expired,
            self::Cancelled,
        ], true);
    }
}
