<?php

namespace App\Services\Google;

use App\Models\SubjectOffering;
use App\Services\Students\SubjectOfferingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GoogleClassroomMatchingService
{
    public function __construct(
        protected SubjectOfferingService $offerings,
    ) {
    }

    /**
     * @param  array<string, mixed>  $course
     * @return array<string, mixed>
     */
    public function matchCourse(array $course, ?int $sectionId = null, ?int $departmentId = null): array
    {
        $candidates = $this->candidateOfferings($course, $sectionId, $departmentId);

        if ($candidates->isEmpty()) {
            return [
                'course' => $course,
                'match' => null,
                'confidence' => 'none',
                'manual_required' => true,
            ];
        }

        $best = $candidates->first();
        $confidence = $this->confidenceLevel($course, $best);

        return [
            'course' => $course,
            'match' => $this->offerings->formatOffering($best),
            'confidence' => $confidence,
            'manual_required' => $confidence === 'low',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     * @return list<array<string, mixed>>
     */
    public function matchCourses(array $courses, ?int $sectionId = null, ?int $departmentId = null): array
    {
        return collect($courses)
            ->map(fn (array $course) => $this->matchCourse($course, $sectionId, $departmentId))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $course
     * @return Collection<int, SubjectOffering>
     */
    protected function candidateOfferings(array $course, ?int $sectionId, ?int $departmentId): Collection
    {
        $name = Str::lower(trim($course['name'] ?? ''));
        $section = Str::lower(trim($course['section'] ?? ''));
        $instructor = Str::lower(trim($course['instructor_name'] ?? ''));

        $query = SubjectOffering::query()
            ->with(['subject', 'instructor.user', 'section.program', 'section.yearLevel'])
            ->where('is_active', true)
            ->whereNotNull('section_id')
            ->whereHas('subject', fn ($q) => $q->where('is_active', true))
            ->whereHas('section', fn ($q) => $q->where('is_active', true));

        if ($departmentId) {
            $query->whereHas('section.program', fn ($q) => $q->where('department_id', $departmentId));
        }

        $offerings = $query->get();

        return $offerings
            ->map(function (SubjectOffering $offering) use ($name, $section, $instructor) {
                $score = 0;
                $subjectName = Str::lower(trim($offering->subject?->name ?? ''));
                $subjectCode = Str::lower(trim($offering->subject?->code ?? ''));
                $offeringSection = Str::lower(trim($offering->sectionDisplayName()));
                $offeringInstructor = Str::lower(trim($offering->instructorDisplayName()));

                if ($name !== '' && ($subjectName === $name || Str::contains($subjectName, $name) || Str::contains($name, $subjectName))) {
                    $score += 40;
                }

                if ($name !== '' && $subjectCode !== '' && Str::contains($name, $subjectCode)) {
                    $score += 20;
                }

                if ($section !== '' && $offeringSection !== '' && ($offeringSection === $section || Str::contains($offeringSection, $section))) {
                    $score += 25;
                }

                if ($instructor !== '' && $offeringInstructor !== '' && ($offeringInstructor === $instructor || Str::contains($offeringInstructor, $instructor) || Str::contains($instructor, $offeringInstructor))) {
                    $score += 25;
                }

                $offering->match_score = $score;

                return $offering;
            })
            ->filter(fn (SubjectOffering $offering) => ($offering->match_score ?? 0) >= 40)
            ->sortByDesc('match_score')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $course
     */
    protected function confidenceLevel(array $course, SubjectOffering $offering): string
    {
        $score = (int) ($offering->match_score ?? 0);

        if ($score >= 80) {
            return 'high';
        }

        if ($score >= 55) {
            return 'medium';
        }

        return 'low';
    }
}
