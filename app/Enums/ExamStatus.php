<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'DRAFT';
    case Scheduled = 'SCHEDULED';
    case Published = 'PUBLISHED';
    case Active = 'ACTIVE';
    case Paused = 'PAUSED';
    case Closed = 'CLOSED';
    case Archived = 'ARCHIVED';
}
