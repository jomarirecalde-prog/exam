<?php

namespace App\Services\Students;

use App\Enums\StudentSubjectChangeRequestStatus;
use App\Enums\StudentSubjectEnrollmentStatus;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSubject;
use App\Models\StudentSubjectChangeRequest;
use App\Models\Subject;
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
    ) {}

    public function subjectVerificationRequired(): bool
    {
        return (bool) SystemSetting::getValue('subject_verification_required', true);
    }

    /**
     * @param  array<int, int|string>  $subjectIds
     * @return array<int, int>
     */
    public function validateSubjectIds(array $subjectIds): array
    {
        $ids = collect($subjectIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            throw ValidationException::withMessages([
                'subject_ids' => 'Please select at least one enrolled subject.',
            ]);
        }

        $validIds = Subject::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalid = array_diff($ids, $validIds);

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'subject_ids' => 'One or more selected subjects are invalid or unavailable.',
            ]);
        }

        return $validIds;
    }

    /**
     * @return array{recommended: Collection<int, Subject>, other: Collection<int, Subject>}
     */
    public function subjectsForRegistration(
        int $sectionId,
        int $departmentId,
        ?string $search = null,
        bool $browseAll = false,
    ): array {
        $section = Section::query()->with('program')->findOrFail($sectionId);

        $recommendedIds = DB::table('subject_section')
            ->where('section_id', $sectionId)
            ->when($section->academic_year_id, fn ($q) => $q->where('academic_year_id', $section->academic_year_id))
            ->when($section->semester_id, fn ($q) => $q->where('semester_id', $section->semester_id))
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $recommendedQuery = Subject::query()
            ->where('is_active', true)
            ->whereIn('id', $recommendedIds !== [] ? $recommendedIds : [0])
            ->orderBy('code');

        $recommended = $this->applySubjectSearch($recommendedQuery, $search)->get(['id', 'code', 'name', 'units', 'department_id']);

        $otherQuery = Subject::query()
            ->where('is_active', true)
            ->when($recommendedIds !== [], fn ($q) => $q->whereNotIn('id', $recommendedIds))
            ->when(! $browseAll, function ($q) use ($departmentId) {
                $q->where(function ($inner) use ($departmentId) {
                    $inner->where('department_id', $departmentId)
                        ->orWhereNull('department_id');
                });
            })
            ->orderBy('code');

        $other = $this->applySubjectSearch($otherQuery, $search)->limit(100)->get(['id', 'code', 'name', 'units', 'department_id']);

        return [
            'recommended' => $recommended,
            'other' => $other,
        ];
    }

    /**
     * @param  array<int, int>  $subjectIds
     */
    public function syncDeclaredEnrollments(
        Student $student,
        array $subjectIds,
        ?int $academicYearId = null,
        ?int $semesterId = null,
        ?User $actor = null,
    ): void {
        $academicYearId ??= $student->section?->academic_year_id
            ?? $this->academic->currentAcademicYear()?->id;
        $semesterId ??= $student->section?->semester_id
            ?? $this->academic->currentSemester()?->id;

        abort_unless($academicYearId && $semesterId, 422, 'Unable to determine the current academic period.');

        $subjectIds = $this->validateSubjectIds($subjectIds);
        $initialStatus = $this->subjectVerificationRequired()
            ? StudentSubjectEnrollmentStatus::PendingVerification
            : StudentSubjectEnrollmentStatus::Verified;

        DB::transaction(function () use ($student, $subjectIds, $academicYearId, $semesterId, $initialStatus, $actor) {
            foreach ($subjectIds as $subjectId) {
                StudentSubject::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'subject_id' => $subjectId,
                        'academic_year_id' => $academicYearId,
                        'semester_id' => $semesterId,
                    ],
                    [
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
                    'subject_ids' => $subjectIds,
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
                'academic_year_id' => $enrollment->academic_year_id,
                'semester_id' => $enrollment->semester_id,
            ],
        );

        return $enrollment->fresh(['subject', 'student']);
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
                'reason' => $enrollment->rejection_reason,
            ],
        );

        return $enrollment->fresh(['subject', 'student']);
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

    public function addEnrollment(Student $student, int $subjectId, User $admin, ?int $academicYearId = null, ?int $semesterId = null): StudentSubject
    {
        $academicYearId ??= $this->academic->currentAcademicYear()?->id;
        $semesterId ??= $this->academic->currentSemester()?->id;

        $this->validateSubjectIds([$subjectId]);

        $enrollment = StudentSubject::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'subject_id' => $subjectId,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
            ],
            [
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
                'subject_id' => $subjectId,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
            ],
        );

        return $enrollment->load('subject');
    }

    public function removeEnrollment(StudentSubject $enrollment, User $admin): void
    {
        $details = [
            'student_id' => $enrollment->student?->student_id,
            'subject_id' => $enrollment->subject_id,
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
     * @param  array<int, int>  $addSubjectIds
     * @param  array<int, int>  $removeSubjectIds
     */
    public function submitChangeRequest(
        Student $student,
        array $addSubjectIds,
        array $removeSubjectIds,
        ?string $reason = null,
        ?int $academicYearId = null,
        ?int $semesterId = null,
    ): StudentSubjectChangeRequest {
        $academicYearId ??= $this->academic->currentAcademicYear()?->id;
        $semesterId ??= $this->academic->currentSemester()?->id;

        $addSubjectIds = collect($addSubjectIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $removeSubjectIds = collect($removeSubjectIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if ($addSubjectIds !== []) {
            $addSubjectIds = $this->validateSubjectIds($addSubjectIds);
        }

        if ($addSubjectIds === [] && $removeSubjectIds === []) {
            throw ValidationException::withMessages([
                'subject_ids' => 'Please select at least one subject to add or remove.',
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
            'add_subject_ids' => $addSubjectIds,
            'remove_subject_ids' => $removeSubjectIds,
            'reason' => filled($reason) ? trim($reason) : null,
        ]);

        $this->audit->log(
            $student->user,
            'request_change',
            'student_subjects',
            StudentSubjectChangeRequest::class,
            $request->id,
            [
                'add_subject_ids' => $addSubjectIds,
                'remove_subject_ids' => $removeSubjectIds,
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
            foreach ($request->remove_subject_ids ?? [] as $subjectId) {
                $enrollment = StudentSubject::query()
                    ->where('student_id', $request->student_id)
                    ->where('subject_id', (int) $subjectId)
                    ->where('academic_year_id', $request->academic_year_id)
                    ->where('semester_id', $request->semester_id)
                    ->first();

                if ($enrollment) {
                    $this->removeEnrollment($enrollment, $admin);
                }
            }

            foreach ($request->add_subject_ids ?? [] as $subjectId) {
                $this->addEnrollment(
                    $request->student,
                    (int) $subjectId,
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

    protected function applySubjectSearch($query, ?string $search)
    {
        if (! filled($search)) {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function ($q) use ($term) {
            $q->where('code', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
    }
}
