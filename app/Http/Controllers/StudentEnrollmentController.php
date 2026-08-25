<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Services\Students\SubjectOfferingService;
use App\Services\Students\AcademicLookupService;
use App\Services\Students\StudentEnrollmentService;
use App\Services\Students\StudentSubjectEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentEnrollmentController extends Controller
{
    public function __construct(
        protected StudentEnrollmentService $enrollmentService,
        protected AcademicLookupService $academicLookup,
        protected StudentSubjectEnrollmentService $subjectEnrollments,
        protected SubjectOfferingService $offerings,
    ) {}

    public function index(Request $request): View
    {
        $student = $this->studentProfile($request);

        $academicYearId = $request->integer('academic_year_id') ?: $this->academicLookup->currentAcademicYear()?->id;
        $semesterId = $request->integer('semester_id') ?: $this->academicLookup->currentSemester(
            $academicYearId ? AcademicYear::query()->find($academicYearId) : null
        )?->id;

        $enrollments = $this->enrollmentService->enrollments($student, $academicYearId, $semesterId);

        return view('pages.student-enrollment.index', [
            'enrollments' => $enrollments,
            'academicYears' => AcademicYear::query()->orderByDesc('is_current')->orderByDesc('name')->get(),
            'semesters' => Semester::query()->orderBy('order')->get(['id', 'academic_year_id', 'name']),
            'academicYearId' => $academicYearId,
            'semesterId' => $semesterId,
            'subjectVerificationRequired' => $this->subjectEnrollments->subjectVerificationRequired(),
            'hasPendingChangeRequest' => $this->enrollmentService->hasPendingChangeRequest($student),
        ]);
    }

    public function show(Request $request, Subject $subject): View
    {
        $student = $this->studentProfile($request);

        [$academicYearId, $semesterId] = $this->termFromRequest($request);

        $enrollment = $this->enrollmentService->assertEnrolled(
            $student,
            $subject,
            $academicYearId,
            $semesterId,
        );

        $examinations = \App\Models\Examination::query()
            ->visibleToStudent($student)
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->orderBy('title')
            ->get();

        return view('pages.student-enrollment.show', [
            'enrollment' => $enrollment,
            'subject' => $enrollment['subject'],
            'section' => $enrollment['section'],
            'instructors' => $enrollment['instructors'],
            'academicYear' => $enrollment['academic_year'],
            'semester' => $enrollment['semester'],
            'academicYearId' => $academicYearId,
            'semesterId' => $semesterId,
            'examinations' => $examinations,
            'subjectVerificationRequired' => $this->subjectEnrollments->subjectVerificationRequired(),
        ]);
    }

    public function changeRequestForm(Request $request): View
    {
        $student = $this->studentProfile($request);

        abort_if($this->enrollmentService->hasPendingChangeRequest($student), 422, 'You already have a pending subject change request.');

        $academicYearId = $this->academicLookup->currentAcademicYear()?->id;
        $semesterId = $this->academicLookup->currentSemester()?->id;

        $currentEnrollments = $this->enrollmentService->enrollments($student, $academicYearId, $semesterId);
        $availableOfferings = $academicYearId && $semesterId
            ? $this->offerings->offeringsForChangeRequest($student, $academicYearId, $semesterId)
            : collect();

        return view('pages.student-enrollment.change-request', [
            'student' => $student,
            'currentEnrollments' => $currentEnrollments,
            'availableOfferings' => $availableOfferings,
            'academicYearId' => $academicYearId,
            'semesterId' => $semesterId,
        ]);
    }

    public function submitChangeRequest(Request $request): RedirectResponse
    {
        $student = $this->studentProfile($request);

        $data = $request->validate([
            'add_subject_offering_ids' => ['nullable', 'array'],
            'add_subject_offering_ids.*' => ['integer', 'exists:subject_instructor,id'],
            'remove_subject_offering_ids' => ['nullable', 'array'],
            'remove_subject_offering_ids.*' => ['integer', 'exists:subject_instructor,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->subjectEnrollments->submitChangeRequest(
            $student,
            $data['add_subject_offering_ids'] ?? [],
            $data['remove_subject_offering_ids'] ?? [],
            $data['reason'] ?? null,
        );

        return redirect()
            ->route('student.enrollment.index')
            ->with('status', 'Your subject change request has been submitted for review.');
    }

    protected function studentProfile(Request $request): Student
    {
        $student = $request->user()?->student;

        abort_unless($student, 403);

        return $student;
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function termFromRequest(Request $request): array
    {
        $academicYearId = $request->integer('academic_year_id');
        $semesterId = $request->integer('semester_id');

        abort_unless($academicYearId && $semesterId, 404);

        return [$academicYearId, $semesterId];
    }
}
