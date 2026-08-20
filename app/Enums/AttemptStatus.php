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
}
