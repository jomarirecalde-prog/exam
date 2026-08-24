<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use App\Services\Instructors\InstructorTeachingService;
use App\Services\Students\AcademicLookupService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstructorTeachingController extends Controller
{
    public function __construct(
        protected InstructorTeachingService $teachingService,
        protected AcademicLookupService $academicLookup,
    ) {}

    public function index(Request $request): View
    {
        $instructor = $this->instructorProfile($request);

        $academicYearId = $request->integer('academic_year_id') ?: $this->academicLookup->currentAcademicYear()?->id;
        $semesterId = $request->integer('semester_id') ?: $this->academicLookup->currentSemester(
            $academicYearId ? AcademicYear::query()->find($academicYearId) : null
        )?->id;

        $assignments = $this->teachingService->assignments($instructor, $academicYearId, $semesterId);

        return view('pages.instructor-teaching.index', [
            'assignments' => $assignments,
            'academicYears' => AcademicYear::query()->orderByDesc('is_current')->orderByDesc('name')->get(),
            'semesters' => Semester::query()->orderBy('order')->get(['id', 'academic_year_id', 'name']),
            'academicYearId' => $academicYearId,
            'semesterId' => $semesterId,
        ]);
    }

    public function show(Request $request, Subject $subject): View
    {
        $instructor = $this->instructorProfile($request);

        [$academicYearId, $semesterId] = $this->termFromRequest($request);

        $this->teachingService->assertCanAccess($instructor, $subject, $academicYearId, $semesterId);

        $sections = $this->teachingService->sectionsForSubject(
            $instructor,
            $subject,
            $academicYearId,
            $semesterId,
        );

        $academicYear = AcademicYear::query()->find($academicYearId);
        $semester = Semester::query()->find($semesterId);

        return view('pages.instructor-teaching.show', [
            'subject' => $subject->load('department'),
            'sections' => $sections,
            'academicYear' => $academicYear,
            'semester' => $semester,
            'academicYearId' => $academicYearId,
            'semesterId' => $semesterId,
        ]);
    }

    public function section(Request $request, Subject $subject, Section $section): View
    {
        $instructor = $this->instructorProfile($request);

        [$academicYearId, $semesterId] = $this->termFromRequest($request);

        $this->teachingService->assertCanAccessSection(
            $instructor,
            $subject,
            $section,
            $academicYearId,
            $semesterId,
        );

        $students = $this->teachingService->studentsForSection($section->id, $academicYearId, $semesterId);

        return view('pages.instructor-teaching.section', [
            'subject' => $subject->load('department'),
            'section' => $section->load(['program', 'yearLevel', 'academicYear', 'semester']),
            'students' => $students,
            'academicYearId' => $academicYearId,
            'semesterId' => $semesterId,
        ]);
    }

    protected function instructorProfile(Request $request)
    {
        $instructor = $request->user()?->instructor;

        abort_unless($instructor, 403);

        return $instructor;
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
