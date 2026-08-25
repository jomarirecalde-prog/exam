<?php

namespace App\Services\Academic;

use App\Models\Examination;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Support\DeletionAnalysis;

class ProgramDeletionService
{
    public function analyze(Program $program): DeletionAnalysis
    {
        $blockers = [];

        $studentCount = Student::query()
            ->whereNull('deleted_at')
            ->where('program_id', $program->id)
            ->count();

        if ($studentCount > 0) {
            $blockers[] = ['label' => $studentCount === 1 ? 'Student' : 'Students', 'count' => $studentCount];
        }

        $sectionCount = Section::query()->where('program_id', $program->id)->count();
        if ($sectionCount > 0) {
            $blockers[] = ['label' => $sectionCount === 1 ? 'Section' : 'Sections', 'count' => $sectionCount];
        }

        $examinationCount = Examination::query()
            ->where(function ($query) use ($program) {
                $query->whereHas('section', fn ($sectionQuery) => $sectionQuery->where('program_id', $program->id))
                    ->orWhereHas('sections', fn ($sectionQuery) => $sectionQuery->where('program_id', $program->id));
            })
            ->count();

        if ($examinationCount > 0) {
            $blockers[] = ['label' => $examinationCount === 1 ? 'Examination' : 'Examinations', 'count' => $examinationCount];
        }

        return new DeletionAnalysis(
            canDelete: $blockers === [],
            recordType: 'program',
            recordName: $program->name,
            recordDetail: $program->code,
            warningMessage: 'This will deactivate the program. It will no longer be available for new sections or student assignments.',
            blockers: $blockers,
            confirmLabel: 'Delete Program',
        );
    }

    public function delete(Program $program): bool
    {
        $analysis = $this->analyze($program);

        if (! $analysis->canDelete) {
            return false;
        }

        $program->update(['is_active' => false]);

        return true;
    }
}
