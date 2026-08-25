<?php

namespace App\Models;

use App\Enums\StudentSubjectChangeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSubjectChangeRequest extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year_id',
        'semester_id',
        'status',
        'add_subject_ids',
        'remove_subject_ids',
        'reason',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentSubjectChangeRequestStatus::class,
            'add_subject_ids' => 'array',
            'remove_subject_ids' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
