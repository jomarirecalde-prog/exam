<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectOffering extends Model
{
    public const TBA_EMPLOYEE_ID = 'TBA-0000';

    protected $table = 'subject_instructor';

    protected $fillable = [
        'subject_id',
        'instructor_id',
        'section_id',
        'academic_year_id',
        'semester_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function instructorDisplayName(): string
    {
        if ($this->instructor?->employee_id === self::TBA_EMPLOYEE_ID) {
            return 'To Be Announced';
        }

        return $this->instructor?->user?->fullName() ?: 'To Be Announced';
    }

    public function sectionDisplayName(): string
    {
        return $this->section?->displayName() ?: '—';
    }
}
