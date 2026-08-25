<?php

namespace App\Enums;

enum AttemptFinalizationStatus: string
{
    case Submitted = 'submitted';
    case AutoSubmitted = 'auto_submitted';
    case PendingReview = 'pending_review';
    case EndedByInstructor = 'ended_by_instructor';
    case EndedByDeadline = 'ended_by_deadline';
}
