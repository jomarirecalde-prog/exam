<?php

namespace App\Services\Examinations;

use App\Enums\AttemptStatus;
use App\Enums\ExamDeadlinePolicy;
use App\Enums\ExamStatus;
use App\Models\Examination;
use Carbon\Carbon;

class ExaminationScheduleService
{
    public function syncSchedule(Examination $examination, array $data, bool $publishing): void
    {
        $immediate = (bool) ($data['availability_immediate'] ?? true);
        $availableFrom = $immediate ? now() : $this->parseDateTime($data['available_from_date'] ?? null, $data['available_from_time'] ?? null);
        $deadlineAt = $this->parseDateTime($data['deadline_date'] ?? null, $data['deadline_time'] ?? null);

        $policy = ExamDeadlinePolicy::tryFrom((string) ($data['deadline_policy'] ?? ''))
            ?? ExamDeadlinePolicy::AllowActiveFinish;

        $updates = [
            'available_from' => $availableFrom,
            'deadline_at' => $deadlineAt,
            'deadline_policy' => $policy,
        ];

        if ($publishing) {
            $updates['status'] = $this->resolvePublishedStatus($availableFrom);
        }

        $examination->update($updates);
    }

    public function resolvePublishedStatus(?Carbon $availableFrom): ExamStatus
    {
        if ($availableFrom && $availableFrom->isFuture()) {
            return ExamStatus::Scheduled;
        }

        return ExamStatus::Active;
    }

    public function activateScheduledExaminations(): int
    {
        $count = 0;

        Examination::query()
            ->where('status', ExamStatus::Scheduled)
            ->whereNotNull('available_from')
            ->where('available_from', '<=', now())
            ->where(function ($query) {
                $query->whereNull('deadline_at')->orWhere('deadline_at', '>', now());
            })
            ->each(function (Examination $examination) use (&$count) {
                $examination->update(['status' => ExamStatus::Active]);
                $count++;
            });

        return $count;
    }

    public function refreshExaminationStatus(Examination $examination): Examination
    {
        if (in_array($examination->status, [ExamStatus::Draft, ExamStatus::Ended, ExamStatus::Archived], true)) {
            return $examination;
        }

        if ($examination->status === ExamStatus::Scheduled && $this->hasStarted($examination)) {
            $examination->update(['status' => ExamStatus::Active]);

            return $examination->fresh();
        }

        if ($this->isPastDeadline($examination) && ! in_array($examination->status, [ExamStatus::Closed, ExamStatus::Expired, ExamStatus::Ended], true)) {
            $status = $examination->deadline_policy === ExamDeadlinePolicy::StopAll
                ? ExamStatus::Closed
                : ExamStatus::Closed;

            $examination->update(['status' => $status]);

            return $examination->fresh();
        }

        return $examination;
    }

    public function hasStarted(Examination $examination): bool
    {
        if (! $examination->available_from) {
            return true;
        }

        return now()->greaterThanOrEqualTo($examination->available_from);
    }

    public function isPastDeadline(Examination $examination): bool
    {
        return $examination->deadline_at !== null && now()->greaterThan($examination->deadline_at);
    }

    public function isBeforeStart(Examination $examination): bool
    {
        return $examination->available_from !== null && now()->lessThan($examination->available_from);
    }

    public function canStartNewAttempt(Examination $examination): bool
    {
        $examination = $this->refreshExaminationStatus($examination);

        if (! $examination->status->allowsNewAttempts()) {
            return false;
        }

        if ($this->isBeforeStart($examination)) {
            return false;
        }

        if ($this->isPastDeadline($examination)) {
            return false;
        }

        if (in_array($examination->status, [ExamStatus::Ended, ExamStatus::Closed, ExamStatus::Expired], true)) {
            return false;
        }

        return true;
    }

    public function canContinueActiveAttempt(Examination $examination): bool
    {
        if (in_array($examination->status, [ExamStatus::Ended, ExamStatus::Closed, ExamStatus::Expired], true)) {
            return false;
        }

        if ($this->isPastDeadline($examination)) {
            return $examination->deadline_policy === ExamDeadlinePolicy::AllowActiveFinish;
        }

        return true;
    }

    public function deadlineRemainingSeconds(Examination $examination): ?int
    {
        if (! $examination->deadline_at) {
            return null;
        }

        return max(0, now()->diffInSeconds($examination->deadline_at, false));
    }

    public function effectiveExpiresAt(Examination $examination, Carbon $startedAt): Carbon
    {
        $durationMinutes = max(1, (int) ($examination->duration_minutes ?: config('examination.default_duration_minutes', 60)));
        $expiresAt = $startedAt->copy()->addMinutes($durationMinutes);

        if ($examination->deadline_at && $examination->deadline_policy === ExamDeadlinePolicy::StopAll) {
            return $expiresAt->min($examination->deadline_at);
        }

        return $expiresAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function schedulePayload(Examination $examination): array
    {
        $examination->loadMissing('endedBy');

        return [
            'available_from' => $examination->available_from?->toIso8601String(),
            'available_from_formatted' => $examination->available_from?->format('F j, Y \– g:i A'),
            'deadline_at' => $examination->deadline_at?->toIso8601String(),
            'deadline_at_formatted' => $examination->deadline_at?->format('F j, Y \– g:i A'),
            'deadline_policy' => $examination->deadline_policy?->value ?? ExamDeadlinePolicy::AllowActiveFinish->value,
            'deadline_policy_label' => $examination->deadline_policy?->label() ?? ExamDeadlinePolicy::AllowActiveFinish->label(),
            'deadline_remaining_seconds' => $this->deadlineRemainingSeconds($examination),
            'status' => $examination->status->value,
            'status_label' => $examination->status->label(),
            'is_ended' => in_array($examination->status, [ExamStatus::Ended, ExamStatus::Closed, ExamStatus::Expired], true),
            'ended_at' => $examination->ended_at?->toIso8601String(),
            'ended_at_formatted' => $examination->ended_at?->format('F j, Y \– g:i A'),
            'end_reason' => $examination->end_reason,
            'ended_by' => $examination->endedBy?->name,
        ];
    }

    public function denyStartReason(Examination $examination): string
    {
        $examination = $this->refreshExaminationStatus($examination);

        if ($this->isBeforeStart($examination)) {
            $when = $examination->available_from?->format('F j, Y \– g:i A') ?? 'the scheduled start time';

            return "This examination is not yet available. It opens on {$when}.";
        }

        if ($this->isPastDeadline($examination) || in_array($examination->status, [ExamStatus::Closed, ExamStatus::Expired], true)) {
            $deadline = $examination->deadline_at?->format('F j, Y \– g:i A') ?? 'the configured deadline';

            return "EXAMINATION CLOSED\n\nThe deadline for this examination has passed.\n\nDeadline: {$deadline}\n\nContact your instructor if you believe this is an error.";
        }

        if ($examination->status === ExamStatus::Ended) {
            return 'This examination has been ended by your instructor.';
        }

        if ($examination->status === ExamStatus::Scheduled) {
            return 'This examination is scheduled and not yet open.';
        }

        return 'This examination is not currently open.';
    }

    protected function parseDateTime(?string $date, ?string $time): ?Carbon
    {
        if (! $date) {
            return null;
        }

        $time = $time ?: '00:00';
        $parsed = Carbon::parse("{$date} {$time}");

        return $parsed->isValid() ? $parsed : null;
    }
}
