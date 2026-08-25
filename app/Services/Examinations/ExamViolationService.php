<?php

namespace App\Services\Examinations;

use App\Enums\AttemptStatus;
use App\Enums\ViolationType;
use App\Models\ExaminationAttempt;
use App\Models\ExamViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ExamViolationService
{
    public function __construct(
        protected ExaminationAttemptService $attempts,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{recorded: bool, violation?: ExamViolation, warning_count: int, max_warnings: int, locked: bool, message?: string, duplicate?: bool}
     */
    public function recordViolation(
        ExaminationAttempt $attempt,
        ViolationType $type,
        ?string $clientEventId = null,
        array $metadata = [],
    ): array {
        if ($attempt->status !== AttemptStatus::InProgress) {
            return [
                'recorded' => false,
                'warning_count' => (int) $attempt->warning_count,
                'max_warnings' => $attempt->maxWarnings(),
                'locked' => $attempt->status === AttemptStatus::LockedViolationLimit,
                'message' => 'Examination is not active.',
            ];
        }

        if ($clientEventId) {
            $existing = ExamViolation::query()
                ->where('examination_attempt_id', $attempt->id)
                ->where('client_event_id', $clientEventId)
                ->first();

            if ($existing) {
                return $this->buildResponse($attempt->fresh(), $existing, duplicate: true);
            }
        }

        if ($this->isDuplicateWithinWindow($attempt, $type)) {
            return [
                'recorded' => false,
                'warning_count' => (int) $attempt->warning_count,
                'max_warnings' => $attempt->maxWarnings(),
                'locked' => false,
                'duplicate' => true,
                'message' => $type->message(),
            ];
        }

        return DB::transaction(function () use ($attempt, $type, $clientEventId, $metadata) {
            $attempt = ExaminationAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status !== AttemptStatus::InProgress) {
                return [
                    'recorded' => false,
                    'warning_count' => (int) $attempt->warning_count,
                    'max_warnings' => $attempt->maxWarnings(),
                    'locked' => $attempt->status === AttemptStatus::LockedViolationLimit,
                ];
            }

            try {
                $this->attempts->saveAnswersBulk($attempt, $metadata['pending_answers'] ?? []);
            } catch (InvalidArgumentException $exception) {
                Log::warning('Could not persist pending answers while recording violation.', [
                    'attempt_id' => $attempt->id,
                    'message' => $exception->getMessage(),
                ]);
            }

            $newWarningCount = min($attempt->maxWarnings(), (int) $attempt->warning_count + 1);

            $violation = ExamViolation::create([
                'examination_attempt_id' => $attempt->id,
                'student_id' => $attempt->student_id,
                'examination_id' => $attempt->examination_id,
                'violation_type' => $type,
                'warning_number' => $newWarningCount,
                'detected_at' => now(),
                'metadata' => $metadata ?: null,
                'client_event_id' => $clientEventId,
            ]);

            $updates = [
                'warning_count' => $newWarningCount,
                'suspicious_activity_count' => (int) $attempt->suspicious_activity_count + 1,
            ];

            if (in_array($type, [ViolationType::TabOrWindowSwitch, ViolationType::PageLeave], true)) {
                $updates['tab_switch_count'] = (int) $attempt->tab_switch_count + 1;
            }

            $locked = false;

            if ($newWarningCount >= $attempt->maxWarnings()) {
                $updates['status'] = AttemptStatus::LockedViolationLimit;
                $updates['locked_at'] = now();
                $updates['lock_reason'] = 'Maximum violation warnings reached ('.$newWarningCount.'/'.$attempt->maxWarnings().')';
                $locked = true;
            }

            $attempt->update($updates);

            app(ExamAttemptProgressService::class)->recordProgress($attempt->fresh());

            return $this->buildResponse($attempt->fresh(), $violation, locked: $locked);
        });
    }

    protected function isDuplicateWithinWindow(ExaminationAttempt $attempt, ViolationType $type): bool
    {
        $windowSeconds = (int) config('examination.violation_dedup_seconds', 3);
        $typesToDedupe = ViolationType::focusLossTypes();

        if (! in_array($type, $typesToDedupe, true)) {
            return false;
        }

        return ExamViolation::query()
            ->where('examination_attempt_id', $attempt->id)
            ->whereIn('violation_type', array_map(fn (ViolationType $t) => $t->value, $typesToDedupe))
            ->where('detected_at', '>=', now()->subSeconds($windowSeconds))
            ->exists();
    }

    /**
     * @return array{recorded: bool, violation?: ExamViolation, warning_count: int, max_warnings: int, locked: bool, message?: string, duplicate?: bool}
     */
    protected function buildResponse(
        ExaminationAttempt $attempt,
        ExamViolation $violation,
        bool $locked = false,
        bool $duplicate = false,
    ): array {
        return [
            'recorded' => ! $duplicate,
            'duplicate' => $duplicate,
            'violation' => $violation,
            'warning_count' => (int) $attempt->warning_count,
            'max_warnings' => $attempt->maxWarnings(),
            'locked' => $locked || $attempt->status === AttemptStatus::LockedViolationLimit,
            'message' => $violation->violation_type->message(),
            'violation_type' => $violation->violation_type->value,
            'violation_label' => $violation->violation_type->label(),
        ];
    }
}
