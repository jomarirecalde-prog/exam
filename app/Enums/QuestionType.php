<?php

namespace App\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case MultipleSelect = 'multiple_select';
    case TrueFalse = 'true_false';
    case Identification = 'identification';
    case ShortAnswer = 'short_answer';
    case Essay = 'essay';
    case Matching = 'matching';
    case Enumeration = 'enumeration';
    case FillBlank = 'fill_blank';

    public function requiresManualGrading(): bool
    {
        return in_array($this, [self::Essay, self::ShortAnswer], true);
    }

    public function isObjective(): bool
    {
        return ! $this->requiresManualGrading();
    }
}
