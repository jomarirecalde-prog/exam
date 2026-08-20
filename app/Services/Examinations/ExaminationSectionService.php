<?php

namespace App\Services\Examinations;

use App\Models\Examination;
use App\Models\Instructor;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExaminationSectionService
{
    public function filterSections(
        User $user,
        int $academicYearId,
        int $semesterId,
        int $subjectId,
        int $programId,
        int $yearLevelId,
        ?string $search = null,
    ): Collection {
        $query = Section::query()
            ->where('is_active', true)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->where('program_id', $programId)
            ->where('year_level_id', $yearLevelId);

        $this->constrainToSubject($query, $subjectId, $academicYearId, $semesterId);
        $this->constrainToInstructor($query, $user, $subjectId, $academicYearId, $semesterId);

        if (filled($search)) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get(['id', 'name', 'code', 'program_id', 'year_level_id']);
    }

    public function present(Collection $sections): array
    {
        return $sections->map(fn (Section $section) => [
            'id' => $section->id,
            'name' => $section->displayName(),
            'code' => $section->code,
            'label' => $section->displayName(),
        ])->values()->all();
    }

    public function assertAssignable(
        User $user,
        array $sectionIds,
        int $academicYearId,
        int $semesterId,
        int $subjectId,
        ?int $programId = null,
        ?int $yearLevelId = null,
    ): void {
        $sectionIds = $this->uniqueIds($sectionIds);

        if ($sectionIds === []) {
            throw ValidationException::withMessages([
                'section_ids' => 'Please select at least one section before continuing.',
            ]);
        }

        $sections = Section::query()
            ->whereIn('id', $sectionIds)
            ->get();

        if ($sections->count() !== count($sectionIds)) {
            throw ValidationException::withMessages([
                'section_ids' => 'One or more selected sections could not be found.',
            ]);
        }

        $allowedIds = $this->filterSections(
            $user,
            $academicYearId,
            $semesterId,
            $subjectId,
            $programId ?: (int) $sections->first()?->program_id,
            $yearLevelId ?: (int) $sections->first()?->year_level_id,
        )->pluck('id')->map(fn ($id) => (int) $id)->all();

        $programs = $sections->pluck('program_id')->unique()->values();
        $yearLevels = $sections->pluck('year_level_id')->unique()->values();

        if ($programId && $programs->contains(fn ($id) => (int) $id !== $programId)) {
            throw ValidationException::withMessages([
                'section_ids' => 'Selected sections must belong to the chosen program.',
            ]);
        }

        if ($yearLevelId && $yearLevels->contains(fn ($id) => (int) $id !== $yearLevelId)) {
            throw ValidationException::withMessages([
                'section_ids' => 'Selected sections must belong to the chosen year level.',
            ]);
        }

        $incompatible = $sections->first(function (Section $section) use ($academicYearId, $semesterId) {
            return (int) $section->academic_year_id !== $academicYearId
                || (int) $section->semester_id !== $semesterId
                || ! $section->is_active;
        });

        if ($incompatible) {
            throw ValidationException::withMessages([
                'section_ids' => 'Selected sections must match the academic year and semester for this examination.',
            ]);
        }

        $unauthorized = array_values(array_diff($sectionIds, $allowedIds));

        if ($unauthorized !== []) {
            throw ValidationException::withMessages([
                'section_ids' => 'You do not have permission to assign one or more selected sections.',
            ]);
        }
    }

    public function sync(Examination $examination, array $sectionIds, bool $protectAttempted = true): void
    {
        $sectionIds = $this->uniqueIds($sectionIds);

        if ($protectAttempted) {
            $blocked = $this->protectedSectionIds($examination, $sectionIds);

            if ($blocked !== []) {
                $names = Section::query()
                    ->whereIn('id', $blocked)
                    ->orderBy('name')
                    ->pluck('name')
                    ->filter()
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'section_ids' => $names !== ''
                        ? "Cannot remove {$names} because students from this section have already started or submitted the examination."
                        : 'Cannot remove a section that already has examination attempts.',
                ]);
            }
        }

        $examination->sections()->sync($sectionIds);

        $examination->forceFill([
            'section_id' => $sectionIds[0] ?? null,
            'needs_section_review' => $sectionIds === [],
        ])->save();
    }

    public function protectedSectionIds(Examination $examination, array $desiredSectionIds = []): array
    {
        $current = $examination->sections()->pluck('sections.id')->map(fn ($id) => (int) $id)->all();
        $removing = array_values(array_diff($current, $this->uniqueIds($desiredSectionIds)));

        if ($removing === []) {
            return [];
        }

        $attemptedStudentIds = $examination->attempts()->pluck('student_id')->unique()->all();

        if ($attemptedStudentIds === []) {
            return [];
        }

        $attemptedSectionIds = Student::query()
            ->whereIn('id', $attemptedStudentIds)
            ->pluck('section_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $historical = DB::table('student_sections')
            ->whereIn('student_id', $attemptedStudentIds)
            ->where('academic_year_id', $examination->academic_year_id)
            ->where('semester_id', $examination->semester_id)
            ->pluck('section_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_intersect($removing, array_unique(array_merge($attemptedSectionIds, $historical))));
    }

    public function uniqueIds(array $sectionIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $sectionIds))));
    }

    protected function constrainToSubject(Builder $query, int $subjectId, int $academicYearId, int $semesterId): void
    {
        $hasAssignments = DB::table('subject_section')
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->exists();

        if (! $hasAssignments) {
            return;
        }

        $query->whereIn('id', function ($sub) use ($subjectId, $academicYearId, $semesterId) {
            $sub->select('section_id')
                ->from('subject_section')
                ->where('subject_id', $subjectId)
                ->where('academic_year_id', $academicYearId)
                ->where('semester_id', $semesterId);
        });
    }

    protected function constrainToInstructor(
        Builder $query,
        User $user,
        int $subjectId,
        int $academicYearId,
        int $semesterId,
    ): void {
        if ($user->hasAnyRole(['superadmin', 'admin'])) {
            return;
        }

        $instructor = $user->instructor;

        if (! $instructor instanceof Instructor) {
            $query->whereRaw('1 = 0');

            return;
        }

        $assignments = DB::table('subject_instructor')
            ->where('instructor_id', $instructor->id)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->get();

        if ($assignments->isEmpty()) {
            return;
        }

        $assignedSectionIds = $assignments
            ->pluck('section_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($assignedSectionIds === []) {
            return;
        }

        $query->whereIn('id', $assignedSectionIds);
    }
}
