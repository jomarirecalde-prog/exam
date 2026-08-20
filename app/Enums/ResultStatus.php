<?php

namespace App\Enums;

enum ResultStatus: string
{
    case Passed = 'PASSED';
    case Failed = 'FAILED';
    case PendingGrading = 'PENDING_GRADING';
    case ForReview = 'FOR_REVIEW';
}
