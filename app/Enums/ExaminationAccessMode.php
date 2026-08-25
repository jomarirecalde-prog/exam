<?php

namespace App\Enums;

enum ExaminationAccessMode: string
{
    case SubjectOnly = 'subject_only';
    case SubjectAndSections = 'subject_and_sections';
    case SpecificStudents = 'specific_students';

    public function label(): string
    {
        return match ($this) {
            self::SubjectOnly => 'All students enrolled in this subject',
            self::SubjectAndSections => 'Only selected sections (must also be enrolled in subject)',
            self::SpecificStudents => 'Specific students',
        };
    }
}
