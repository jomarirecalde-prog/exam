<?php

namespace App\Services\Academic;

use App\Enums\ExamStatus;
use App\Models\Examination;
use App\Models\Section;
use App\Models\Student;
use App\Support\DeletionAnalysis;
use Illuminate\Support\Facades\DB;

class SectionDeletionService
{
    public function analyze(Section $section): DeletionAnalysis
    {
        $blockers = [];

        $primaryStudentIds = Student::query()
            ->whereNull('deleted_at')
            ->where('section_id', $section->id)
            ->pluck('id');

        $pivotStudentIds = DB::table('student_sections')
            ->where('section_id', $section->id)
            ->pluck('student_id');

        $studentCount = $primaryStudentIds->merge($pivotStudentIds)->unique()->count();

        if ($studentCount > 0) {
            $blockers[] = ['label' => $studentCount === 1 ? 'Assigned Student' : 'Assigned Students', 'count' => $studentCount];
        }

        $activeExaminationCount = Examination::query()
            ->where(function ($query) use ($section) {
                $query->where('section_id', $section->id)
                    ->orWhereHas('sections', fn ($sectionQuery) => $sectionQuery->where('sections.id', $section->id));
            })
            ->whereIn('status', [
                ExamStatus::Published,
                ExamStatus::Active,
                ExamStatus::Paused,
            ])
            ->count();

        if ($activeExaminationCount > 0) {
            $blockers[] = ['label' => $activeExaminationCount === 1 ? 'Active Examination' : 'Active Examinations', 'count' => $activeExaminationCount];
        }

        $warning = $studentCount > 0
            ? "This section currently has {$studentCount} assigned ".($studentCount === 1 ? 'student' : 'students').'. You must reassign the students before this section can be deleted.'
            : 'This will deactivate the section. Subject enrollments for irregular students will be preserved.';

        return new DeletionAnalysis(
            canDelete: $blockers === [],
            recordType: 'section',
            recordName: $section->displayName(),
            recordDetail: $section->program?->code,
            warningMessage: $warning,
            blockers: $blockers,
            confirmLabel: 'Delete Section',
        );
    }

    public function delete(Section $section): bool
    {
        $analysis = $this->analyze($section);

        if (! $analysis->canDelete) {
            return false;
        }

        $section->update(['is_active' => false]);

        return true;
    }
}
