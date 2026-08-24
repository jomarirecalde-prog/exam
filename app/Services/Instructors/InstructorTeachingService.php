<?php

namespace App\Services\Instructors;

use App\Models\AcademicYear;
use App\Models\Instructor;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Services\Students\AcademicLookupService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InstructorTeachingService
{
    public function __construct(protected AcademicLookupService $academicLookup) {}

    /**
     * @return Collection<int, array{
     *     subject: Subject,
     *     academic_year: AcademicYear|null,
     *     semester: Semester|null,
     *     academic_year_id: int,
     *     semester_id: int,
     *     sections: Collection<int, array{section: Section, student_count: int}>,
     *     section_count: int,
     *     student_count: int
     * }>
     */
    public function assignments(
        Instructor $instructor,
        ?int $academicYearId = null,
        ?int $semesterId = null,
    ): Collection {
        $academicYearId ??= $this->academicLookup->currentAcademicYear()?->id;
        $semesterId ??= $this->academicLookup->currentSemester(
            $academicYearId ? AcademicYear::query()->find($academicYearId) : null
        )?->id;

        $query = DB::table('subject_instructor')
            ->where('instructor_id', $instructor->id);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $subjects = Subject::query()
            ->whereIn('id', $rows->pluck('subject_id')->unique())
            ->get()
            ->keyBy('id');

        $academicYears = AcademicYear::query()
            ->whereIn('id', $rows->pluck('academic_year_id')->unique())
            ->get()
            ->keyBy('id');

        $semesters = Semester::query()
            ->whereIn('id', $rows->pluck('semester_id')->unique())
            ->get()
            ->keyBy('id');

        return $rows
            ->groupBy(fn ($row) => "{$row->subject_id}-{$row->academic_year_id}-{$row->semester_id}")
            ->map(function (Collection $assignmentRows) use ($instructor, $subjects, $academicYears, $semesters) {
                $first = $assignmentRows->first();
                $subject = $subjects->get($first->subject_id);

                if (! $subject) {
                    return null;
                }

                $sections = $this->sectionsForAssignment(
                    $instructor,
                    $subject,
                    (int) $first->academic_year_id,
                    (int) $first->semester_id,
                    $assignmentRows,
                );

                return [
                    'subject' => $subject,
                    'academic_year_id' => (int) $first->academic_year_id,
                    'semester_id' => (int) $first->semester_id,
                    'academic_year' => $academicYears->get($first->academic_year_id),
                    'semester' => $semesters->get($first->semester_id),
                    'sections' => $sections,
                    'section_count' => $sections->count(),
                    'student_count' => $sections->sum('student_count'),
                ];
            })
            ->filter()
            ->sortBy(fn (array $assignment) => $assignment['subject']->code)
            ->values();
    }

    /**
     * @return Collection<int, array{section: Section, student_count: int}>
     */
    public function sectionsForSubject(
        Instructor $instructor,
        Subject $subject,
        int $academicYearId,
        int $semesterId,
    ): Collection {
        $this->assertCanAccess($instructor, $subject, $academicYearId, $semesterId);

        $assignmentRows = DB::table('subject_instructor')
            ->where('instructor_id', $instructor->id)
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->get();

        return $this->sectionsForAssignment(
            $instructor,
            $subject,
            $academicYearId,
            $semesterId,
            $assignmentRows,
        );
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentsForSection(int $sectionId, int $academicYearId, int $semesterId): Collection
    {
        $studentIdsFromPivot = DB::table('student_sections')
            ->where('section_id', $sectionId)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->pluck('student_id');

        return Student::query()
            ->with(['user', 'program', 'yearLevel'])
            ->where('is_active', true)
            ->where(function ($query) use ($sectionId, $studentIdsFromPivot) {
                $query->where('section_id', $sectionId);

                if ($studentIdsFromPivot->isNotEmpty()) {
                    $query->orWhereIn('id', $studentIdsFromPivot);
                }
            })
            ->orderBy('student_id')
            ->get();
    }

    public function assertCanAccess(
        Instructor $instructor,
        Subject $subject,
        int $academicYearId,
        int $semesterId,
    ): void {
        $exists = DB::table('subject_instructor')
            ->where('instructor_id', $instructor->id)
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->exists();

        abort_unless($exists, 403);
    }

    public function assertCanAccessSection(
        Instructor $instructor,
        Subject $subject,
        Section $section,
        int $academicYearId,
        int $semesterId,
    ): void {
        $this->assertCanAccess($instructor, $subject, $academicYearId, $semesterId);

        $allowed = $this->sectionsForSubject($instructor, $subject, $academicYearId, $semesterId)
            ->contains(fn (array $entry) => (int) $entry['section']->id === (int) $section->id);

        abort_unless($allowed, 403);
    }

    /**
     * @return Collection<int, array{section: Section, student_count: int}>
     */
    protected function sectionsForAssignment(
        Instructor $instructor,
        Subject $subject,
        int $academicYearId,
        int $semesterId,
        Collection $assignmentRows,
    ): Collection {
        $query = Section::query()
            ->with(['program', 'yearLevel'])
            ->where('is_active', true)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId);

        $hasSubjectSections = DB::table('subject_section')
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->exists();

        if ($hasSubjectSections) {
            $query->whereIn('id', function ($sub) use ($subject, $academicYearId, $semesterId) {
                $sub->select('section_id')
                    ->from('subject_section')
                    ->where('subject_id', $subject->id)
                    ->where('academic_year_id', $academicYearId)
                    ->where('semester_id', $semesterId);
            });
        }

        $assignedSectionIds = $assignmentRows
            ->pluck('section_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($assignedSectionIds !== []) {
            $query->whereIn('id', $assignedSectionIds);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(fn (Section $section) => [
                'section' => $section,
                'student_count' => $this->studentsForSection($section->id, $academicYearId, $semesterId)->count(),
            ]);
    }
}
