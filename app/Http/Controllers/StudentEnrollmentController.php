<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Services\Students\AcademicLookupService;
use App\Services\Students\StudentEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentEnrollmentController extends Controller
{
    public function __construct(
        protected StudentEnrollmentService $enrollmentService,
        protected AcademicLookupService $academicLookup,
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

        return view('pages.student-enrollment.show', [
            'enrollment' => $enrollment,
            'subject' => $enrollment['subject'],
            'section' => $enrollment['section'],
            'instructors' => $enrollment['instructors'],
            'academicYear' => $enrollment['academic_year'],
            'semester' => $enrollment['semester'],
            'academicYearId' => $academicYearId,
            'semesterId' => $semesterId,
        ]);
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
