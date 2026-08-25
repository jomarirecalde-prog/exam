<?php

namespace App\Services\Students;

use App\Enums\StudentSubjectChangeRequestStatus;
use App\Enums\StudentSubjectEnrollmentStatus;
use App\Models\Student;
use App\Models\StudentSubject;
use App\Models\StudentSubjectChangeRequest;
use App\Models\SubjectOffering;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentSubjectEnrollmentService
{
    public function __construct(
        protected AcademicLookupService $academic,
        protected AuditLogger $audit,
        protected SubjectOfferingService $offerings,
    ) {}

    public function subjectVerificationRequired(): bool
    {
        return (bool) SystemSetting::getValue('subject_verification_required', true);
    }

    /**
     * @param  array<int, int|string>  $offeringIds
     * @return Collection<int, SubjectOffering>
     */
    public function validateOfferingIds(array $offeringIds): Collection
    {
        try {
            return $this->offerings->validateOfferingIds($offeringIds);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            if ($exception->getStatusCode() === 422) {
                throw ValidationException::withMessages([
                    'subject_offering_ids' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    /**
     * @return array{recommended: Collection<int, array<string, mixed>>, other: Collection<int, array<string, mixed>>}
     */
    public function offeringsForRegistration(
        int $sectionId,
        int $departmentId,
        ?string $search = null,
        bool $browseAll = false,
    ): array {
        return $this->offerings->offeringsForRegistration($sectionId, $departmentId, $search, $browseAll);
    }

    /**
     * @param  array<int, int|string>  $offeringIds
     */
    public function syncDeclaredEnrollments(
        Student $student,
        array $offeringIds,
        ?int $academicYearId = null,
        ?int $semesterId = null,
        ?User $actor = null,
    ): void {
        $academicYearId ??= $student->section?->academic_year_id
            ?? $this->academic->currentAcademicYear()?->id;
        $semesterId ??= $student->section?->semester_id
            ?? $this->academic->currentSemester()?->id;

        abort_unless($academicYearId && $semesterId, 422, 'Unable to determine the current academic period.');

        $offerings = $this->validateOfferingIds($offeringIds);

        $invalidTerm = $offerings->first(function (SubjectOffering $offering) use ($academicYearId, $semesterId) {
            return (int) $offering->academic_year_id !== (int) $academicYearId
                || (int) $offering->semester_id !== (int) $semesterId;
        });

        if ($invalidTerm) {
            throw ValidationException::withMessages([
                'subject_offering_ids' => 'One or more selected subject offerings are not available for the current academic period.',
            ]);
        }

        $initialStatus = $this->subjectVerificationRequired()
            ? StudentSubjectEnrollmentStatus::PendingVerification
            : StudentSubjectEnrollmentStatus::Verified;

        DB::transaction(function () use ($student, $offerings, $academicYearId, $semesterId, $initialStatus, $actor) {
            foreach ($offerings as $offering) {
                StudentSubject::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'subject_offering_id' => $offering->id,
                        'academic_year_id' => $academicYearId,
                        'semester_id' => $semesterId,
                    ],
                    [
                        'subject_id' => $offering->subject_id,
                        'status' => $initialStatus,
                        'verified_at' => $initialStatus === StudentSubjectEnrollmentStatus::Verified ? now() : null,
                        'verified_by' => $initialStatus === StudentSubjectEnrollmentStatus::Verified ? $actor?->id : null,
                        'rejection_reason' => null,
                    ],
                );
            }
        });

        if ($actor) {
            $this->audit->log(
                $actor,
                'declare_subjects',
                'student_subjects',
                Student::class,
                $student->id,
                [
                    'subject_offering_ids' => $offerings->pluck('id')->all(),
                    'academic_year_id' => $academicYearId,
                    'semester_id' => $semesterId,
                    'status' => $initialStatus->value,
                ],
            );
        }
    }

    public function verifyEnrollment(StudentSubject $enrollment, User $admin): StudentSubject
    {
        $enrollment->update([
            'status' => StudentSubjectEnrollmentStatus::Verified,
            'verified_at' => now(),
            'verified_by' => $admin->id,
            'rejection_reason' => null,
        ]);

        $this->audit->log(
            $admin,
            'verify',
            'student_subjects',
            StudentSubject::class,
            $enrollment->id,
            [
                'student_id' => $enrollment->student?->student_id,
                'subject_id' => $enrollment->subject_id,
                'subject_offering_id' => $enrollment->subject_offering_id,
                'academic_year_id' => $enrollment->academic_year_id,
                'semester_id' => $enrollment->semester_id,
            ],
        );

        return $enrollment->fresh(['subject', 'subjectOffering.instructor.user', 'subjectOffering.section', 'student']);
    }

    public function rejectEnrollment(StudentSubject $enrollment, User $admin, ?string $reason = null): StudentSubject
    {
        $enrollment->update([
            'status' => StudentSubjectEnrollmentStatus::Rejected,
            'verified_at' => null,
            'verified_by' => $admin->id,
            'rejection_reason' => filled($reason) ? trim($reason) : null,
        ]);

        $this->audit->log(
            $admin,
            'reject',
            'student_subjects',
            StudentSubject::class,
            $enrollment->id,
            [
                'student_id' => $enrollment->student?->student_id,
                'subject_id' => $enrollment->subject_id,
                'subject_offering_id' => $enrollment->subject_offering_id,
                'reason' => $enrollment->rejection_reason,
            ],
        );

        return $enrollment->fresh(['subject', 'subjectOffering.instructor.user', 'subjectOffering.section', 'student']);
    }

    public function verifyAllForStudent(Student $student, User $admin, ?int $academicYearId = null, ?int $semesterId = null): int
    {
        $query = $student->subjectEnrollments()
            ->where('status', StudentSubjectEnrollmentStatus::PendingVerification);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        $count = 0;

        $query->get()->each(function (StudentSubject $enrollment) use ($admin, &$count) {
            $this->verifyEnrollment($enrollment, $admin);
            $count++;
        });

        return $count;
    }

    public function addEnrollment(Student $student, int $offeringId, User $admin, ?int $academicYearId = null, ?int $semesterId = null): StudentSubject
    {
        $academicYearId ??= $this->academic->currentAcademicYear()?->id;
        $semesterId ??= $this->academic->currentSemester()?->id;

        $offering = $this->validateOfferingIds([$offeringId])->first();

        abort_unless($offering, 422);

        $enrollment = StudentSubject::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'subject_offering_id' => $offering->id,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
            ],
            [
                'subject_id' => $offering->subject_id,
                'status' => StudentSubjectEnrollmentStatus::Verified,
                'verified_at' => now(),
                'verified_by' => $admin->id,
                'rejection_reason' => null,
            ],
        );

        $this->audit->log(
            $admin,
            'add',
            'student_subjects',
            StudentSubject::class,
            $enrollment->id,
            [
                'student_id' => $student->student_id,
                'subject_id' => $offering->subject_id,
                'subject_offering_id' => $offering->id,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
            ],
        );

        return $enrollment->load(['subject', 'subjectOffering.instructor.user', 'subjectOffering.section']);
    }

    public function removeEnrollment(StudentSubject $enrollment, User $admin): void
    {
        $details = [
            'student_id' => $enrollment->student?->student_id,
            'subject_id' => $enrollment->subject_id,
            'subject_offering_id' => $enrollment->subject_offering_id,
            'academic_year_id' => $enrollment->academic_year_id,
            'semester_id' => $enrollment->semester_id,
        ];

        $enrollment->delete();

        $this->audit->log(
            $admin,
            'remove',
            'student_subjects',
            StudentSubject::class,
            $enrollment->id,
            $details,
        );
    }

    /**
     * @param  array<int, int|string>  $addOfferingIds
     * @param  array<int, int|string>  $removeOfferingIds
     */
    public function submitChangeRequest(
        Student $student,
        array $addOfferingIds,
        array $removeOfferingIds,
        ?string $reason = null,
        ?int $academicYearId = null,
        ?int $semesterId = null,
    ): StudentSubjectChangeRequest {
        $academicYearId ??= $this->academic->currentAcademicYear()?->id;
        $semesterId ??= $this->academic->currentSemester()?->id;

        $addOfferingIds = collect($addOfferingIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $removeOfferingIds = collect($removeOfferingIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if ($addOfferingIds !== []) {
            $this->validateOfferingIds($addOfferingIds);
        }

        if ($addOfferingIds === [] && $removeOfferingIds === []) {
            throw ValidationException::withMessages([
                'subject_offering_ids' => 'Please select at least one subject offering to add or remove.',
            ]);
        }

        $pendingExists = $student->subjectChangeRequests()
            ->where('status', StudentSubjectChangeRequestStatus::Pending)
            ->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages([
                'request' => 'You already have a pending subject change request.',
            ]);
        }

        $request = StudentSubjectChangeRequest::create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYearId,
            'semester_id' => $semesterId,
            'status' => StudentSubjectChangeRequestStatus::Pending,
            'add_subject_offering_ids' => $addOfferingIds,
            'remove_subject_offering_ids' => $removeOfferingIds,
            'reason' => filled($reason) ? trim($reason) : null,
        ]);

        $this->audit->log(
            $student->user,
            'request_change',
            'student_subjects',
            StudentSubjectChangeRequest::class,
            $request->id,
            [
                'add_subject_offering_ids' => $addOfferingIds,
                'remove_subject_offering_ids' => $removeOfferingIds,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
            ],
        );

        return $request;
    }

    public function approveChangeRequest(StudentSubjectChangeRequest $request, User $admin, ?string $notes = null): void
    {
        abort_unless($request->status === StudentSubjectChangeRequestStatus::Pending, 422);

        DB::transaction(function () use ($request, $admin, $notes) {
            foreach ($request->remove_subject_offering_ids ?? [] as $offeringId) {
                $enrollment = StudentSubject::query()
                    ->where('student_id', $request->student_id)
                    ->where('subject_offering_id', (int) $offeringId)
                    ->where('academic_year_id', $request->academic_year_id)
                    ->where('semester_id', $request->semester_id)
                    ->first();

                if ($enrollment) {
                    $this->removeEnrollment($enrollment, $admin);
                }
            }

            foreach ($request->add_subject_offering_ids ?? [] as $offeringId) {
                $this->addEnrollment(
                    $request->student,
                    (int) $offeringId,
                    $admin,
                    $request->academic_year_id,
                    $request->semester_id,
                );
            }

            $request->update([
                'status' => StudentSubjectChangeRequestStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_notes' => filled($notes) ? trim($notes) : null,
            ]);
        });

        $this->audit->log(
            $admin,
            'approve_change_request',
            'student_subjects',
            StudentSubjectChangeRequest::class,
            $request->id,
            ['student_id' => $request->student?->student_id],
        );
    }

    public function rejectChangeRequest(StudentSubjectChangeRequest $request, User $admin, ?string $notes = null): void
    {
        abort_unless($request->status === StudentSubjectChangeRequestStatus::Pending, 422);

        $request->update([
            'status' => StudentSubjectChangeRequestStatus::Rejected,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_notes' => filled($notes) ? trim($notes) : null,
        ]);

        $this->audit->log(
            $admin,
            'reject_change_request',
            'student_subjects',
            StudentSubjectChangeRequest::class,
            $request->id,
            ['student_id' => $request->student?->student_id],
        );
    }

    public function isEnrolledInOffering(
        Student $student,
        int $offeringId,
        int $academicYearId,
        int $semesterId,
        ?bool $verificationRequired = null,
    ): bool {
        $verificationRequired ??= $this->subjectVerificationRequired();

        $enrollment = StudentSubject::query()
            ->where('student_id', $student->id)
            ->where('subject_offering_id', $offeringId)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->first();

        return $enrollment?->isActiveForExamAccess($verificationRequired) ?? false;
    }

    public function isEnrolledInSubject(
        Student $student,
        int $subjectId,
        int $academicYearId,
        int $semesterId,
        ?bool $verificationRequired = null,
    ): bool {
        $verificationRequired ??= $this->subjectVerificationRequired();

        $enrollment = StudentSubject::query()
            ->where('student_id', $student->id)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->first();

        return $enrollment?->isActiveForExamAccess($verificationRequired) ?? false;
    }

    /**
     * @return Collection<int, int>
     */
    public function activeOfferingIds(
        Student $student,
        int $academicYearId,
        int $semesterId,
        ?bool $verificationRequired = null,
    ): Collection {
        $verificationRequired ??= $this->subjectVerificationRequired();

        return $student->subjectEnrollments()
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->get()
            ->filter(fn (StudentSubject $enrollment) => $enrollment->isActiveForExamAccess($verificationRequired))
            ->pluck('subject_offering_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function activeSubjectIds(
        Student $student,
        int $academicYearId,
        int $semesterId,
        ?bool $verificationRequired = null,
    ): Collection {
        $verificationRequired ??= $this->subjectVerificationRequired();

        return $student->subjectEnrollments()
            ->with('subject')
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->get()
            ->filter(fn (StudentSubject $enrollment) => $enrollment->isActiveForExamAccess($verificationRequired))
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }
}
