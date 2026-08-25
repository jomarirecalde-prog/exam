<?php

namespace App\Enums;

enum ExamEndPolicy: string
{
    case AutoSubmit = 'auto_submit';
    case SaveForReview = 'save_for_review';

    public function label(): string
    {
        return match ($this) {
            self::AutoSubmit => 'End and automatically submit their current answers',
            self::SaveForReview => 'End and save their examination for instructor review',
        };
    }
}
