<?php

namespace App\Services\Academic;

use App\Models\Examination;
use App\Models\Question;
use App\Models\Subject;
use App\Support\DeletionAnalysis;

class SubjectDeletionService
{
    public function analyze(Subject $subject): DeletionAnalysis
    {
        $blockers = [];

        $enrollmentCount = $subject->studentEnrollments()
            ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
            ->count();

        if ($enrollmentCount > 0) {
            $blockers[] = ['label' => $enrollmentCount === 1 ? 'Student Enrollment' : 'Student Enrollments', 'count' => $enrollmentCount];
        }

        $examinationCount = Examination::query()->where('subject_id', $subject->id)->count();

        if ($examinationCount > 0) {
            $blockers[] = ['label' => $examinationCount === 1 ? 'Examination' : 'Examinations', 'count' => $examinationCount];
        }

        $questionCount = Question::query()->where('subject_id', $subject->id)->count();

        if ($questionCount > 0) {
            $blockers[] = ['label' => $questionCount === 1 ? 'Question Bank Item' : 'Question Bank Items', 'count' => $questionCount];
        }

        return new DeletionAnalysis(
            canDelete: $blockers === [],
            recordType: 'subject',
            recordName: $subject->name,
            recordDetail: $subject->code,
            warningMessage: 'This will deactivate the subject. It will no longer be available for new examinations or student enrollments. Existing instructor and section assignments will be preserved.',
            blockers: $blockers,
            confirmLabel: 'Delete Subject',
        );
    }

    public function delete(Subject $subject): bool
    {
        $analysis = $this->analyze($subject);

        if (! $analysis->canDelete) {
            return false;
        }

        $subject->update(['is_active' => false]);

        return true;
    }
}
