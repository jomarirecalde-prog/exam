<?php

namespace App\Services\Students;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\YearLevel;
use Illuminate\Database\Eloquent\Collection;

class AcademicLookupService
{
    public function currentAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()
            ->where('is_active', true)
            ->where('is_current', true)
            ->first()
            ?? AcademicYear::query()->where('is_active', true)->latest('id')->first();
    }

    public function currentSemester(?AcademicYear $academicYear = null): ?Semester
    {
        $academicYear ??= $this->currentAcademicYear();

        if (! $academicYear) {
            return null;
        }

        return Semester::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->where('is_current', true)
            ->first()
            ?? Semester::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('is_active', true)
                ->orderByDesc('order')
                ->first();
    }

    /**
     * @return Collection<int, Department>
     */
    public function activeDepartments(): Collection
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /**
     * @return Collection<int, Program>
     */
    public function programsForDepartment(int $departmentId): Collection
    {
        return Program::query()
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'department_id', 'code', 'name']);
    }

    /**
     * @return Collection<int, YearLevel>
     */
    public function yearLevelsForProgram(int $programId): Collection
    {
        return YearLevel::query()
            ->where('program_id', $programId)
            ->where('is_active', true)
            ->orderBy('level')
            ->get(['id', 'program_id', 'name', 'level']);
    }

    /**
     * @return Collection<int, Section>
     */
    public function sectionsForProgramAndYearLevel(int $programId, int $yearLevelId): Collection
    {
        $baseQuery = Section::query()
            ->where('program_id', $programId)
            ->where('year_level_id', $yearLevelId)
            ->where('is_active', true);

        $academicYear = $this->currentAcademicYear();
        $semester = $this->currentSemester($academicYear);

        if ($academicYear && $semester) {
            $filtered = (clone $baseQuery)
                ->where('academic_year_id', $academicYear->id)
                ->where('semester_id', $semester->id)
                ->orderBy('name')
                ->get(['id', 'program_id', 'year_level_id', 'name', 'code']);

            if ($filtered->isNotEmpty()) {
                return $filtered;
            }
        }

        return $baseQuery->orderBy('name')->get(['id', 'program_id', 'year_level_id', 'name', 'code']);
    }

    public function sectionBelongsToProgramAndYearLevel(int $sectionId, int $programId, int $yearLevelId): bool
    {
        return Section::query()
            ->whereKey($sectionId)
            ->where('program_id', $programId)
            ->where('year_level_id', $yearLevelId)
            ->where('is_active', true)
            ->exists();
    }

    public function programBelongsToDepartment(int $programId, int $departmentId): bool
    {
        return Program::query()
            ->whereKey($programId)
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->exists();
    }

    public function yearLevelBelongsToProgram(int $yearLevelId, int $programId): bool
    {
        return YearLevel::query()
            ->whereKey($yearLevelId)
            ->where('program_id', $programId)
            ->where('is_active', true)
            ->exists();
    }
}
