<?php

namespace App\Services\Students;

use App\Models\AcademicYear;
use App\Models\Instructor;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentEnrollmentService
{
    public function __construct(protected AcademicLookupService $academicLookup) {}

    /**
     * @return Collection<int, array{
     *     subject: Subject,
     *     section: Section,
     *     instructors: Collection<int, Instructor>,
     *     academic_year: AcademicYear|null,
     *     semester: Semester|null,
     *     academic_year_id: int,
     *     semester_id: int
     * }>
     */
    public function enrollments(
        Student $student,
        ?int $academicYearId = null,
        ?int $semesterId = null,
    ): Collection {
        $academicYearId ??= $this->academicLookup->currentAcademicYear()?->id;
        $semesterId ??= $this->academicLookup->currentSemester(
            $academicYearId ? AcademicYear::query()->find($academicYearId) : null
        )?->id;

        if (! $academicYearId || ! $semesterId) {
            return collect();
        }

        $sectionIds = $student->accessibleSectionIds($academicYearId, $semesterId);

        if ($sectionIds === []) {
            return collect();
        }

        $pairs = $this->enrollmentPairs($sectionIds, $academicYearId, $semesterId);

        if ($pairs->isEmpty()) {
            return collect();
        }

        $subjects = Subject::query()
            ->with('department')
            ->whereIn('id', $pairs->pluck('subject_id')->unique())
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $sections = Section::query()
            ->with(['program', 'yearLevel'])
            ->whereIn('id', $pairs->pluck('section_id')->unique())
            ->get()
            ->keyBy('id');

        $academicYear = AcademicYear::query()->find($academicYearId);
        $semester = Semester::query()->find($semesterId);

        $instructorRows = DB::table('subject_instructor')
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->whereIn('subject_id', $pairs->pluck('subject_id')->unique())
            ->get();

        $instructors = Instructor::query()
            ->with(['user', 'department'])
            ->whereIn('id', $instructorRows->pluck('instructor_id')->unique())
            ->get()
            ->keyBy('id');

        return $pairs
            ->map(function (array $pair) use ($subjects, $sections, $instructors, $instructorRows, $academicYear, $semester, $academicYearId, $semesterId) {
                $subject = $subjects->get($pair['subject_id']);
                $section = $sections->get($pair['section_id']);

                if (! $subject || ! $section) {
                    return null;
                }

                $assignedInstructors = $instructorRows
                    ->filter(function ($row) use ($pair) {
                        if ((int) $row->subject_id !== (int) $pair['subject_id']) {
                            return false;
                        }

                        return ! $row->section_id || (int) $row->section_id === (int) $pair['section_id'];
                    })
                    ->pluck('instructor_id')
                    ->unique()
                    ->map(fn ($id) => $instructors->get($id))
                    ->filter()
                    ->values();

                return [
                    'subject' => $subject,
                    'section' => $section,
                    'instructors' => $assignedInstructors,
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                    'academic_year_id' => $academicYearId,
                    'semester_id' => $semesterId,
                ];
            })
            ->filter()
            ->sortBy(fn (array $enrollment) => $enrollment['subject']->code)
            ->values();
    }

    public function findEnrollment(
        Student $student,
        Subject $subject,
        int $academicYearId,
        int $semesterId,
    ): ?array {
        return $this->enrollments($student, $academicYearId, $semesterId)
            ->first(fn (array $enrollment) => (int) $enrollment['subject']->id === (int) $subject->id);
    }

    public function assertEnrolled(
        Student $student,
        Subject $subject,
        int $academicYearId,
        int $semesterId,
    ): array {
        $enrollment = $this->findEnrollment($student, $subject, $academicYearId, $semesterId);

        abort_unless($enrollment, 403);

        return $enrollment;
    }

    /**
     * @param  array<int, int>  $sectionIds
     * @return Collection<int, array{subject_id: int, section_id: int}>
     */
    protected function enrollmentPairs(array $sectionIds, int $academicYearId, int $semesterId): Collection
    {
        $fromSubjectSection = DB::table('subject_section')
            ->whereIn('section_id', $sectionIds)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->get(['subject_id', 'section_id']);

        $fromInstructor = DB::table('subject_instructor')
            ->whereIn('section_id', $sectionIds)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->get(['subject_id', 'section_id']);

        return collect()
            ->merge($fromSubjectSection)
            ->merge($fromInstructor)
            ->map(fn ($row) => [
                'subject_id' => (int) $row->subject_id,
                'section_id' => (int) $row->section_id,
            ])
            ->unique(fn (array $pair) => "{$pair['subject_id']}-{$pair['section_id']}")
            ->values();
    }
}
