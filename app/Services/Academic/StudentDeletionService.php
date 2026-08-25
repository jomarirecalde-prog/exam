<?php

namespace App\Services\Academic;

use App\Models\ExaminationAttempt;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\DeletionAnalysis;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class StudentDeletionService
{
    public function __construct(protected AuditLogger $auditLogger)
    {
    }

    public function analyze(Student $student): DeletionAnalysis
    {
        $student->loadMissing('user');

        $enrollmentCount = $student->subjectEnrollments()->count();
        $attemptCount = ExaminationAttempt::query()->where('student_id', $student->id)->count();
        $gradeCount = Grade::query()->where('student_id', $student->id)->count();
        $changeRequestCount = $student->subjectChangeRequests()->count();

        $relatedSummary = collect([
            $enrollmentCount > 0 ? "{$enrollmentCount} subject ".($enrollmentCount === 1 ? 'enrollment' : 'enrollments') : null,
            $attemptCount > 0 ? "{$attemptCount} examination ".($attemptCount === 1 ? 'attempt' : 'attempts') : null,
            $gradeCount > 0 ? "{$gradeCount} examination ".($gradeCount === 1 ? 'result' : 'results') : null,
            $changeRequestCount > 0 ? "{$changeRequestCount} subject change ".($changeRequestCount === 1 ? 'request' : 'requests') : null,
        ])->filter()->implode(', ');

        $warning = 'This action may affect the student\'s subject enrollments, examination records, and other related data.';
        if ($relatedSummary !== '') {
            $warning .= ' Related records include: '.$relatedSummary.'.';
        }
        $warning .= ' This action cannot be undone unless recovery is enabled. Historical examination and audit data will be preserved.';

        return new DeletionAnalysis(
            canDelete: true,
            recordType: 'student',
            recordName: $student->displayName(),
            recordDetail: 'Student ID: '.$student->student_id,
            warningMessage: $warning,
            blockers: [],
            confirmLabel: 'Delete Student',
        );
    }

    public function delete(Authenticatable $actor, Student $student): void
    {
        DB::transaction(function () use ($actor, $student) {
            $student->loadMissing('user');

            $student->update([
                'is_active' => false,
                'deleted_by' => $actor->getAuthIdentifier(),
            ]);

            $student->delete();

            if ($student->user instanceof User) {
                $student->user->update(['is_active' => false]);
            }
        });

        $this->logDeletion($actor, $student, 'delete', 'deleted');
    }

    public function restore(Authenticatable $actor, Student $student): void
    {
        DB::transaction(function () use ($actor, $student) {
            $student->loadMissing('user');

            $student->restore();
            $student->update([
                'is_active' => true,
                'deleted_by' => null,
            ]);

            if ($student->user instanceof User) {
                $student->user->update(['is_active' => true]);
            }
        });

        $this->logDeletion($actor, $student, 'restore', 'restored');
    }

    public function forceDelete(Authenticatable $actor, Student $student): bool
    {
        $hasAttempts = ExaminationAttempt::query()->where('student_id', $student->id)->exists();
        $hasGrades = Grade::query()->where('student_id', $student->id)->exists();

        if ($hasAttempts || $hasGrades) {
            $this->auditLogger->log(
                $actor,
                'force_delete_blocked',
                'students',
                Student::class,
                $student->id,
                [
                    'name' => $student->displayName(),
                    'student_id' => $student->student_id,
                    'reason' => 'Student has examination history that must be preserved.',
                    'role' => $this->actorRole($actor),
                    'succeeded' => false,
                ],
            );

            return false;
        }

        DB::transaction(function () use ($student) {
            $student->loadMissing('user');

            $user = $student->user;
            $student->subjectEnrollments()->delete();
            $student->subjectChangeRequests()->delete();
            DB::table('student_sections')->where('student_id', $student->id)->delete();

            $student->forceDelete();

            if ($user instanceof User) {
                $user->forceDelete();
            }
        });

        $this->logDeletion($actor, $student, 'force_delete', 'permanently deleted');

        return true;
    }

    protected function logDeletion(Authenticatable $actor, Student $student, string $action, string $outcome): void
    {
        $this->auditLogger->log(
            $actor,
            $action,
            'students',
            Student::class,
            $student->id,
            [
                'name' => $student->displayName(),
                'student_id' => $student->student_id,
                'role' => $this->actorRole($actor),
                'succeeded' => true,
                'outcome' => $outcome,
            ],
        );
    }

    protected function actorRole(Authenticatable $actor): ?string
    {
        if (! method_exists($actor, 'getRoleNames')) {
            return null;
        }

        return $actor->getRoleNames()->first();
    }
}
