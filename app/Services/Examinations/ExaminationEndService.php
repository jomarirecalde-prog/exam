<?php

namespace App\Services\Examinations;

use App\Enums\AttemptFinalizationStatus;
use App\Enums\AttemptStatus;
use App\Enums\ExamDeadlinePolicy;
use App\Enums\ExamEndPolicy;
use App\Enums\ExamStatus;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExaminationEndService
{
    public function __construct(
        protected ExaminationAttemptService $attempts,
        protected ExaminationScheduleService $schedule,
        protected AuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function endExamination(
        Examination $examination,
        User $user,
        ExamEndPolicy $endPolicy,
        ?string $reason = null,
    ): array {
        if (! app(ExaminationAccessService::class)->canManage($user, $examination)) {
            throw new InvalidArgumentException('You are not authorized to end this examination.');
        }

        if (in_array($examination->status, [ExamStatus::Ended, ExamStatus::Closed, ExamStatus::Expired, ExamStatus::Draft, ExamStatus::Archived], true)) {
            throw new InvalidArgumentException('This examination cannot be ended in its current state.');
        }

        $reason = trim((string) $reason) ?: null;

        return DB::transaction(function () use ($examination, $user, $endPolicy, $reason) {
            $examination = Examination::query()->lockForUpdate()->findOrFail($examination->id);
            $endedAt = now();

            $activeAttempts = ExaminationAttempt::query()
                ->where('examination_id', $examination->id)
                ->where('status', AttemptStatus::InProgress)
                ->lockForUpdate()
                ->get();

            $offlineCount = $activeAttempts->filter(fn (ExaminationAttempt $attempt) => $this->isOfflineAttempt($attempt))->count();
            $onlineCount = $activeAttempts->count() - $offlineCount;

            foreach ($activeAttempts as $attempt) {
                $this->finalizeAttempt($attempt, $endPolicy, 'instructor_ended', $endedAt);
            }

            $examination->update([
                'status' => ExamStatus::Ended,
                'ended_at' => $endedAt,
                'ended_by_user_id' => $user->id,
                'end_reason' => $reason,
                'end_policy' => $endPolicy,
            ]);

            $this->audit->log(
                $user,
                'end_examination',
                'examinations',
                Examination::class,
                $examination->id,
                [
                    'title' => $examination->title,
                    'end_policy' => $endPolicy->value,
                    'reason' => $reason,
                    'affected_students' => $activeAttempts->count(),
                    'offline_students' => $offlineCount,
                    'online_students' => $onlineCount,
                ],
            );

            return [
                'examination' => $this->schedule->schedulePayload($examination->fresh(['endedBy'])),
                'affected_students' => $activeAttempts->count(),
                'offline_students' => $offlineCount,
                'online_students' => $onlineCount,
                'ended_at' => $endedAt->toIso8601String(),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function extendDeadline(Examination $examination, User $user, Carbon $newDeadline, ?string $reason = null): array
    {
        if (! app(ExaminationAccessService::class)->canManage($user, $examination)) {
            throw new InvalidArgumentException('You are not authorized to modify this examination deadline.');
        }

        if (! in_array($examination->status, [ExamStatus::Active, ExamStatus::Published, ExamStatus::Scheduled, ExamStatus::Paused], true)) {
            throw new InvalidArgumentException('The deadline can only be extended for active examinations.');
        }

        if ($examination->available_from && $newDeadline->lessThanOrEqualTo($examination->available_from)) {
            throw new InvalidArgumentException('The new deadline must be after the examination start time.');
        }

        if ($newDeadline->lessThanOrEqualTo(now())) {
            throw new InvalidArgumentException('The new deadline must be in the future.');
        }

        $reason = trim((string) $reason) ?: null;
        $previousDeadline = $examination->deadline_at?->toIso8601String();

        $examination->update(['deadline_at' => $newDeadline]);

        if (in_array($examination->status, [ExamStatus::Closed, ExamStatus::Expired], true)) {
            $examination->update(['status' => ExamStatus::Active]);
        }

        $this->audit->log(
            $user,
            'extend_deadline',
            'examinations',
            Examination::class,
            $examination->id,
            [
                'title' => $examination->title,
                'previous_deadline' => $previousDeadline,
                'new_deadline' => $newDeadline->toIso8601String(),
                'reason' => $reason,
            ],
        );

        return $this->schedule->schedulePayload($examination->fresh(['endedBy']));
    }

    public function processDeadline(Examination $examination): int
    {
        if (! $this->schedule->isPastDeadline($examination)) {
            return 0;
        }

        if (in_array($examination->status, [ExamStatus::Ended, ExamStatus::Closed, ExamStatus::Expired, ExamStatus::Draft, ExamStatus::Archived], true)) {
            return 0;
        }

        return DB::transaction(function () use ($examination) {
            $examination = Examination::query()->lockForUpdate()->findOrFail($examination->id);

            if (! $this->schedule->isPastDeadline($examination)) {
                return 0;
            }

            $affected = 0;

            if ($examination->deadline_policy === ExamDeadlinePolicy::StopAll) {
                $activeAttempts = ExaminationAttempt::query()
                    ->where('examination_id', $examination->id)
                    ->where('status', AttemptStatus::InProgress)
                    ->lockForUpdate()
                    ->get();

                foreach ($activeAttempts as $attempt) {
                    $this->finalizeAttempt($attempt, ExamEndPolicy::AutoSubmit, 'deadline_reached', now());
                    $affected++;
                }
            }

            $examination->update(['status' => ExamStatus::Closed]);

            return $affected;
        });
    }

    public function processDueDeadlines(): int
    {
        $total = 0;

        Examination::query()
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', now())
            ->whereNotIn('status', [
                ExamStatus::Ended,
                ExamStatus::Closed,
                ExamStatus::Expired,
                ExamStatus::Draft,
                ExamStatus::Archived,
            ])
            ->each(function (Examination $examination) use (&$total) {
                $total += $this->processDeadline($examination);
            });

        return $total;
    }

    public function syncEndedExaminationForAttempt(ExaminationAttempt $attempt): ?ExaminationAttempt
    {
        $examination = $attempt->examination;

        if (! in_array($examination->status, [ExamStatus::Ended, ExamStatus::Closed], true)) {
            return null;
        }

        if ($attempt->status !== AttemptStatus::InProgress) {
            return $attempt;
        }

        $endPolicy = $examination->end_policy ?? ExamEndPolicy::AutoSubmit;
        $reason = $examination->status === ExamStatus::Closed ? 'deadline_reached' : 'instructor_ended';

        return $this->finalizeAttempt($attempt, $endPolicy, $reason, $examination->ended_at ?? now());
    }

    protected function finalizeAttempt(
        ExaminationAttempt $attempt,
        ExamEndPolicy $endPolicy,
        string $endedReason,
        Carbon $endedAt,
    ): ExaminationAttempt {
        if ($attempt->status !== AttemptStatus::InProgress) {
            return $attempt;
        }

        if ($endPolicy === ExamEndPolicy::AutoSubmit) {
            $attempt->update([
                'ended_at' => $endedAt,
                'ended_reason' => $endedReason,
                'finalization_status' => $endedReason === 'deadline_reached'
                    ? AttemptFinalizationStatus::EndedByDeadline
                    : AttemptFinalizationStatus::EndedByInstructor,
            ]);

            return $this->attempts->submitAttempt($attempt->fresh(), auto: true);
        }

        $attempt->update([
            'status' => AttemptStatus::Submitted,
            'submitted_at' => $endedAt,
            'ended_at' => $endedAt,
            'ended_reason' => $endedReason,
            'finalization_status' => AttemptFinalizationStatus::PendingReview,
            'last_activity_at' => $endedAt,
            'current_question_index' => null,
        ]);

        return $attempt->fresh();
    }

    protected function isOfflineAttempt(ExaminationAttempt $attempt): bool
    {
        return $attempt->connection_status === 'offline'
            || ($attempt->last_activity_at && $attempt->last_activity_at->lt(now()->subMinutes(2))
                && $attempt->connection_status !== 'online');
    }
}
