<?php

namespace App\Services\Examinations;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;

class ExaminationAccessService
{
    public function canManage(User $user, ?Examination $examination = null): bool
    {
        if ($user->hasAnyRole(['superadmin', 'admin'])) {
            return true;
        }

        if (! $user->hasRole('instructor')) {
            return false;
        }

        if (! $examination) {
            return true;
        }

        return $examination->instructor_id
            && $user->instructor
            && (int) $user->instructor->id === (int) $examination->instructor_id;
    }

    public function canTake(User $user, Examination $examination): bool
    {
        if ($user->hasAnyRole(['superadmin', 'admin', 'instructor'])) {
            return true;
        }

        if (! $user->hasRole('student')) {
            return false;
        }

        $student = $user->student;

        if (! $student) {
            return false;
        }

        if ($this->hasInProgressAttempt($student, $examination)) {
            return $this->studentAuthorizedForExamination($student, $examination)
                || $this->hasAttemptHistory($student, $examination);
        }

        if (! $this->studentAssignedToExamination($student, $examination)) {
            return false;
        }

        if (! $this->isPublishedForStudents($examination)) {
            return false;
        }

        if (! $this->isCurrentlyAvailable($examination)) {
            return false;
        }

        return ! $this->hasExceededAttempts($student, $examination);
    }

    public function canViewResult(User $user, Examination $examination): bool
    {
        if ($user->hasAnyRole(['superadmin', 'admin', 'instructor'])) {
            return true;
        }

        if (! $user->hasRole('student')) {
            return false;
        }

        $student = $user->student;

        if (! $student) {
            return false;
        }

        if ($this->hasAttemptHistory($student, $examination) || $this->hasReleasedOrPendingGrade($student, $examination)) {
            return true;
        }

        return $this->studentAssignedToExamination($student, $examination);
    }

    public function denyTakeReason(User $user, Examination $examination): string
    {
        $student = $user->student;

        if (! $student || ! $this->studentAssignedToExamination($student, $examination)) {
            return 'You are not authorized to access this examination.';
        }

        if (! $this->isPublishedForStudents($examination)) {
            return 'This examination is not available.';
        }

        if (! $this->isCurrentlyAvailable($examination)) {
            return 'This examination is not currently open.';
        }

        if ($this->hasExceededAttempts($student, $examination)) {
            return 'You have used all allowed attempts for this examination.';
        }

        return 'You are not authorized to access this examination.';
    }

    public function studentAssignedToExamination(Student $student, Examination $examination): bool
    {
        if ($examination->needs_section_review) {
            return false;
        }

        $sectionIds = $student->accessibleSectionIds(
            $examination->academic_year_id,
            $examination->semester_id,
        );

        if ($sectionIds === []) {
            return false;
        }

        return $examination->sections()
            ->whereIn('sections.id', $sectionIds)
            ->exists();
    }

    public function isPublishedForStudents(Examination $examination): bool
    {
        return in_array($examination->status, [
            ExamStatus::Published,
            ExamStatus::Active,
        ], true);
    }

    public function isCurrentlyAvailable(Examination $examination): bool
    {
        return $this->isPublishedForStudents($examination);
    }

    public function hasExceededAttempts(Student $student, Examination $examination): bool
    {
        $maxAttempts = (int) ($examination->settings?->max_attempts ?: 1);

        if ($maxAttempts < 1) {
            $maxAttempts = 1;
        }

        $used = ExaminationAttempt::query()
            ->where('examination_id', $examination->id)
            ->where('student_id', $student->id)
            ->whereNotIn('status', [AttemptStatus::Cancelled, AttemptStatus::NotStarted])
            ->count();

        return $used >= $maxAttempts;
    }

    protected function studentAuthorizedForExamination(Student $student, Examination $examination): bool
    {
        return $this->studentAssignedToExamination($student, $examination)
            && $this->isPublishedForStudents($examination);
    }

    protected function hasInProgressAttempt(Student $student, Examination $examination): bool
    {
        return ExaminationAttempt::query()
            ->where('examination_id', $examination->id)
            ->where('student_id', $student->id)
            ->where('status', AttemptStatus::InProgress)
            ->exists();
    }

    protected function hasAttemptHistory(Student $student, Examination $examination): bool
    {
        return ExaminationAttempt::query()
            ->where('examination_id', $examination->id)
            ->where('student_id', $student->id)
            ->exists();
    }

    protected function hasReleasedOrPendingGrade(Student $student, Examination $examination): bool
    {
        return Grade::query()
            ->where('examination_id', $examination->id)
            ->where('student_id', $student->id)
            ->exists();
    }
}
