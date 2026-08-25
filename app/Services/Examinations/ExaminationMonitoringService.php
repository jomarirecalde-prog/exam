<?php

namespace App\Services\Examinations;

use App\Enums\AttemptStatus;
use App\Enums\ReactivationWarningMode;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExamReactivationLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExaminationMonitoringService
{
    public function __construct(
        protected ExaminationAccessService $access,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function attemptsForExamination(Examination $examination, User $user): Collection
    {
        $this->assertCanMonitor($user, $examination);

        return ExaminationAttempt::query()
            ->with([
                'student.user',
                'violations' => fn ($q) => $q->latest('detected_at')->limit(1),
            ])
            ->where('examination_id', $examination->id)
            ->whereIn('status', [
                AttemptStatus::InProgress,
                AttemptStatus::LockedViolationLimit,
                AttemptStatus::Submitted,
                AttemptStatus::AutoSubmitted,
            ])
            ->whereNotNull('started_at')
            ->latest('started_at')
            ->get()
            ->map(fn (ExaminationAttempt $attempt) => $this->formatAttemptRow($attempt, $examination));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function violationHistory(ExaminationAttempt $attempt, User $user): Collection
    {
        $this->assertCanMonitor($user, $attempt->examination);

        return $attempt->violations()
            ->orderBy('warning_number')
            ->get()
            ->map(fn ($violation) => [
                'warning_number' => $violation->warning_number,
                'type' => $violation->violation_type->label(),
                'violation_type' => $violation->violation_type->value,
                'detected_at' => $violation->detected_at->format('g:i:s A'),
                'detected_at_iso' => $violation->detected_at->toIso8601String(),
                'metadata' => $violation->metadata,
            ]);
    }

    public function reactivate(
        ExaminationAttempt $attempt,
        User $user,
        string $reason,
        ReactivationWarningMode $warningMode,
        ?int $manualWarningCount = null,
    ): ExaminationAttempt {
        $this->assertCanMonitor($user, $attempt->examination);

        if ($attempt->status !== AttemptStatus::LockedViolationLimit) {
            throw new InvalidArgumentException('Only locked examination attempts can be reactivated.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reactivation reason is required.');
        }

        return DB::transaction(function () use ($attempt, $user, $reason, $warningMode, $manualWarningCount) {
            $attempt = ExaminationAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $previousWarnings = (int) $attempt->warning_count;
            $maxWarnings = $attempt->maxWarnings();

            $newWarnings = match ($warningMode) {
                ReactivationWarningMode::Reset => 0,
                ReactivationWarningMode::Keep => $previousWarnings,
                ReactivationWarningMode::Manual => max(0, min($maxWarnings, (int) $manualWarningCount)),
            };

            $attempt->update([
                'status' => AttemptStatus::InProgress,
                'warning_count' => $newWarnings,
                'locked_at' => null,
                'lock_reason' => null,
                'reactivated_at' => now(),
                'reactivated_by' => $user->id,
                'reactivation_reason' => $reason,
                'reactivation_count' => (int) $attempt->reactivation_count + 1,
            ]);

            ExamReactivationLog::create([
                'examination_attempt_id' => $attempt->id,
                'reactivated_by' => $user->id,
                'reactivation_reason' => $reason,
                'warning_mode' => $warningMode->value,
                'previous_warning_count' => $previousWarnings,
                'new_warning_count' => $newWarnings,
                'reactivated_at' => now(),
            ]);

            return $attempt->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatAttemptRow(ExaminationAttempt $attempt, Examination $examination): array
    {
        $student = $attempt->student;
        $latestViolation = $attempt->violations->first();

        return [
            'attempt_id' => $attempt->id,
            'student_name' => trim(($student?->user?->first_name ?? '').' '.($student?->user?->last_name ?? '')),
            'student_id' => $student?->student_id,
            'examination' => $examination->title,
            'subject_offering' => $examination->subject?->code,
            'warning_count' => (int) $attempt->warning_count,
            'max_warnings' => $attempt->maxWarnings(),
            'latest_violation' => $latestViolation?->violation_type->label(),
            'latest_violation_at' => $latestViolation?->detected_at?->format('M j, g:i A'),
            'status' => $attempt->status->value,
            'status_label' => $this->statusLabel($attempt->status),
            'can_reactivate' => $attempt->status === AttemptStatus::LockedViolationLimit,
            'lock_reason' => $attempt->lock_reason,
        ];
    }

    protected function statusLabel(AttemptStatus $status): string
    {
        return match ($status) {
            AttemptStatus::InProgress => 'Taking Exam',
            AttemptStatus::LockedViolationLimit => 'Locked',
            AttemptStatus::Submitted, AttemptStatus::AutoSubmitted => 'Submitted',
            default => str_replace('_', ' ', $status->value),
        };
    }

    protected function assertCanMonitor(User $user, Examination $examination): void
    {
        if (! $this->access->canMonitor($user, $examination)) {
            throw new InvalidArgumentException('You are not authorized to monitor this examination.');
        }
    }
}
