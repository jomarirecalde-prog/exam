<?php

namespace App\Services\Examinations;

use App\Enums\AttemptStatus;
use App\Enums\ExaminationAccessMode;
use App\Enums\ExamStatus;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\Students\StudentSubjectEnrollmentService;
use Illuminate\Support\Collection;

class ExaminationAccessService
{
    public function __construct(
        protected StudentSubjectEnrollmentService $subjectEnrollments,
    ) {}

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

    public function canMonitor(User $user, Examination $examination): bool
    {
        return $this->canManage($user, $examination);
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

        if ($this->hasLockedAttempt($student, $examination)) {
            return $this->studentAuthorizedForExamination($student, $examination)
                || $this->hasAttemptHistory($student, $examination);
        }

        if ($this->hasResumableNotStartedAttempt($student, $examination)) {
            return $this->studentAuthorizedForExamination($student, $examination);
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

        if (! $student) {
            return 'You are not authorized to access this examination.';
        }

        if (! $this->isEnrolledInExamSubject($student, $examination)) {
            return 'You are not enrolled in the subject for this examination.';
        }

        if ($this->subjectEnrollments->subjectVerificationRequired()
            && ! $this->hasVerifiedSubjectEnrollment($student, $examination)) {
            return 'Your enrollment for this subject is awaiting verification.';
        }

        if (! $this->studentAssignedToExamination($student, $examination)) {
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

        if (! $this->isEnrolledInExamSubject($student, $examination)) {
            return false;
        }

        $accessMode = $examination->access_mode ?? ExaminationAccessMode::SubjectAndSections;

        return match ($accessMode) {
            ExaminationAccessMode::SubjectOnly => true,
            ExaminationAccessMode::SpecificStudents => $examination->assignedStudents()
                ->where('students.id', $student->id)
                ->exists(),
            ExaminationAccessMode::SubjectAndSections => $this->studentInAssignedSections($student, $examination),
        };
    }

    /**
     * @return Collection<int, int>
     */
    public function examinationIdsForSubjectEnrollment(Student $student): Collection
    {
        if (! $student->subjectEnrollments()->exists()) {
            return collect();
        }

        $verificationRequired = $this->subjectEnrollments->subjectVerificationRequired();

        return Examination::query()
            ->where('access_mode', ExaminationAccessMode::SubjectOnly)
            ->get(['id', 'subject_id', 'subject_offering_id', 'academic_year_id', 'semester_id'])
            ->filter(function (Examination $exam) use ($student, $verificationRequired) {
                if ($exam->subject_offering_id) {
                    return $this->subjectEnrollments->isEnrolledInOffering(
                        $student,
                        (int) $exam->subject_offering_id,
                        (int) $exam->academic_year_id,
                        (int) $exam->semester_id,
                        $verificationRequired,
                    );
                }

                return $this->subjectEnrollments->isEnrolledInSubject(
                    $student,
                    (int) $exam->subject_id,
                    (int) $exam->academic_year_id,
                    (int) $exam->semester_id,
                    $verificationRequired,
                );
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function examinationIdsForSubjectAndSection(Student $student): Collection
    {
        if (! $student->subjectEnrollments()->exists()) {
            return $this->legacyExaminationIdsForSections($student);
        }

        $verificationRequired = $this->subjectEnrollments->subjectVerificationRequired();

        return Examination::query()
            ->where('access_mode', ExaminationAccessMode::SubjectAndSections)
            ->with('sections:id')
            ->get(['id', 'subject_id', 'subject_offering_id', 'academic_year_id', 'semester_id'])
            ->filter(function (Examination $exam) use ($student, $verificationRequired) {
                if ($exam->subject_offering_id) {
                    if (! $this->subjectEnrollments->isEnrolledInOffering(
                        $student,
                        (int) $exam->subject_offering_id,
                        (int) $exam->academic_year_id,
                        (int) $exam->semester_id,
                        $verificationRequired,
                    )) {
                        return false;
                    }
                } elseif (! $this->subjectEnrollments->isEnrolledInSubject(
                    $student,
                    (int) $exam->subject_id,
                    (int) $exam->academic_year_id,
                    (int) $exam->semester_id,
                    $verificationRequired,
                )) {
                    return false;
                }

                $sectionIds = $student->accessibleSectionIds(
                    $exam->academic_year_id,
                    $exam->semester_id,
                );

                if ($sectionIds === []) {
                    return false;
                }

                return $exam->sections->pluck('id')->intersect($sectionIds)->isNotEmpty();
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    protected function legacyExaminationIdsForSections(Student $student): Collection
    {
        return Examination::query()
            ->where('access_mode', ExaminationAccessMode::SubjectAndSections)
            ->whereIn('status', [
                ExamStatus::Published,
                ExamStatus::Active,
            ])
            ->with('sections:id')
            ->get(['id', 'academic_year_id', 'semester_id'])
            ->filter(function (Examination $exam) use ($student) {
                $sectionIds = $student->accessibleSectionIds(
                    $exam->academic_year_id,
                    $exam->semester_id,
                );

                if ($sectionIds === []) {
                    return false;
                }

                return $exam->sections->pluck('id')->intersect($sectionIds)->isNotEmpty();
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
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

    protected function isEnrolledInExamSubject(Student $student, Examination $examination): bool
    {
        if ($examination->subject_offering_id) {
            return $this->subjectEnrollments->isEnrolledInOffering(
                $student,
                (int) $examination->subject_offering_id,
                (int) $examination->academic_year_id,
                (int) $examination->semester_id,
            ) || $this->legacySectionSubjectAccess($student, $examination);
        }

        if (! $examination->subject_id) {
            return false;
        }

        return $this->subjectEnrollments->isEnrolledInSubject(
            $student,
            (int) $examination->subject_id,
            (int) $examination->academic_year_id,
            (int) $examination->semester_id,
        ) || $this->legacySectionSubjectAccess($student, $examination);
    }

    protected function hasVerifiedSubjectEnrollment(Student $student, Examination $examination): bool
    {
        if ($examination->subject_offering_id) {
            return $this->subjectEnrollments->isEnrolledInOffering(
                $student,
                (int) $examination->subject_offering_id,
                (int) $examination->academic_year_id,
                (int) $examination->semester_id,
                true,
            );
        }

        return $this->subjectEnrollments->isEnrolledInSubject(
            $student,
            (int) $examination->subject_id,
            (int) $examination->academic_year_id,
            (int) $examination->semester_id,
            true,
        );
    }

    protected function legacySectionSubjectAccess(Student $student, Examination $examination): bool
    {
        if ($student->subjectEnrollments()->exists()) {
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

    protected function studentInAssignedSections(Student $student, Examination $examination): bool
    {
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

    protected function hasLockedAttempt(Student $student, Examination $examination): bool
    {
        return ExaminationAttempt::query()
            ->where('examination_id', $examination->id)
            ->where('student_id', $student->id)
            ->where('status', AttemptStatus::LockedViolationLimit)
            ->exists();
    }

    protected function hasResumableNotStartedAttempt(Student $student, Examination $examination): bool
    {
        return ExaminationAttempt::query()
            ->where('examination_id', $examination->id)
            ->where('student_id', $student->id)
            ->where('status', AttemptStatus::NotStarted)
            ->whereNotNull('policy_accepted_at')
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
