<?php

namespace App\Services\Academic;

use App\Models\Department;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Support\DeletionAnalysis;
use Illuminate\Support\Facades\DB;

class DepartmentDeletionService
{
    public function analyze(Department $department): DeletionAnalysis
    {
        $blockers = [];

        $programCount = $department->programs()->count();
        if ($programCount > 0) {
            $blockers[] = ['label' => $programCount === 1 ? 'Program' : 'Programs', 'count' => $programCount];
        }

        $studentCount = Student::query()
            ->whereNull('deleted_at')
            ->whereHas('program', fn ($query) => $query->where('department_id', $department->id))
            ->count();

        if ($studentCount > 0) {
            $blockers[] = ['label' => $studentCount === 1 ? 'Student' : 'Students', 'count' => $studentCount];
        }

        $sectionCount = Section::query()
            ->whereHas('program', fn ($query) => $query->where('department_id', $department->id))
            ->count();

        if ($sectionCount > 0) {
            $blockers[] = ['label' => $sectionCount === 1 ? 'Section' : 'Sections', 'count' => $sectionCount];
        }

        $subjectCount = $department->subjects()->count();
        if ($subjectCount > 0) {
            $blockers[] = ['label' => $subjectCount === 1 ? 'Subject' : 'Subjects', 'count' => $subjectCount];
        }

        $instructorCount = Instructor::query()->where('department_id', $department->id)->count();
        if ($instructorCount > 0) {
            $blockers[] = ['label' => $instructorCount === 1 ? 'Instructor' : 'Instructors', 'count' => $instructorCount];
        }

        return new DeletionAnalysis(
            canDelete: $blockers === [],
            recordType: 'department',
            recordName: $department->name,
            recordDetail: $department->code,
            warningMessage: 'This will deactivate the department. It will no longer be available for new programs.',
            blockers: $blockers,
            confirmLabel: 'Delete Department',
        );
    }

    public function delete(Department $department): bool
    {
        $analysis = $this->analyze($department);

        if (! $analysis->canDelete) {
            return false;
        }

        $department->update(['is_active' => false]);

        return true;
    }
}
