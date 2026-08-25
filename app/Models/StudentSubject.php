<?php

namespace App\Models;

use App\Enums\StudentSubjectEnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSubject extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'academic_year_id',
        'semester_id',
        'status',
        'verified_at',
        'verified_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentSubjectEnrollmentStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isActiveForExamAccess(bool $verificationRequired): bool
    {
        if ($this->status === StudentSubjectEnrollmentStatus::Rejected) {
            return false;
        }

        if ($verificationRequired) {
            return $this->status === StudentSubjectEnrollmentStatus::Verified;
        }

        return in_array($this->status, [
            StudentSubjectEnrollmentStatus::Verified,
            StudentSubjectEnrollmentStatus::PendingVerification,
        ], true);
    }
}
