<?php

namespace App\Services\Examinations;

use App\Enums\AttemptStatus;
use App\Enums\ViolationType;
use App\Models\ExaminationAttempt;
use App\Models\ExamSyncEvent;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExamAttemptSyncService
{
    public function __construct(
        protected ExaminationAttemptService $attempts,
        protected ExamViolationService $violations,
        protected OfflineExamPreparationService $offlinePrep,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public function syncEvents(ExaminationAttempt $attempt, array $events, string $deviceIdentifier): array
    {
        $this->offlinePrep->assertDeviceAuthorized($attempt, $deviceIdentifier);

        $attempt->loadMissing('examination');

        if ($attempt->status === AttemptStatus::InProgress) {
            app(ExaminationEndService::class)->syncEndedExaminationForAttempt($attempt);
            $attempt = $attempt->fresh();

            if ($attempt->status !== AttemptStatus::InProgress) {
                return [
                    'processed' => 0,
                    'duplicates' => 0,
                    'conflicts' => [],
                    'results' => [],
                    'attempt' => $this->attempts->attemptState($attempt),
                    'examination_ended' => true,
                ];
            }
        }

        return $this->syncEventsInternal($attempt, $events, $deviceIdentifier);
    }

    protected function syncEventsInternal(ExaminationAttempt $attempt, array $events, string $deviceIdentifier): array
    {
        $results = [];
        $conflicts = [];

        DB::transaction(function () use ($attempt, $events, &$results, &$conflicts) {
            foreach ($events as $event) {
                $uuid = (string) ($event['client_event_uuid'] ?? '');
                if ($uuid === '') {
                    continue;
                }

                $existing = ExamSyncEvent::query()->where('client_event_uuid', $uuid)->first();
                if ($existing) {
                    $results[] = [
                        'client_event_uuid' => $uuid,
                        'status' => 'duplicate',
                        'result' => $existing->result,
                    ];

                    continue;
                }

                try {
                    $result = $this->processEvent($attempt, $event);
                    ExamSyncEvent::create([
                        'client_event_uuid' => $uuid,
                        'examination_attempt_id' => $attempt->id,
                        'event_type' => (string) ($event['event_type'] ?? 'unknown'),
                        'payload' => $event['payload'] ?? [],
                        'result' => $result,
                        'processed_at' => now(),
                    ]);
                    $results[] = [
                        'client_event_uuid' => $uuid,
                        'status' => ($result['skipped'] ?? false) ? 'skipped' : 'processed',
                        'result' => $result,
                    ];
                } catch (SyncConflictException $e) {
                    $conflicts[] = [
                        'client_event_uuid' => $uuid,
                        'message' => $e->getMessage(),
                        'details' => $e->details,
                    ];
                } catch (InvalidArgumentException $e) {
                    $results[] = [
                        'client_event_uuid' => $uuid,
                        'status' => 'skipped',
                        'result' => [
                            'skipped' => true,
                            'message' => $e->getMessage(),
                        ],
                    ];
                }
            }

            $attempt->update(['last_synced_at' => now()]);
        });

        $fresh = $attempt->fresh();

        return [
            'processed' => count(array_filter($results, fn ($r) => ($r['status'] ?? '') === 'processed')),
            'duplicates' => count(array_filter($results, fn ($r) => ($r['status'] ?? '') === 'duplicate')),
            'conflicts' => $conflicts,
            'results' => $results,
            'attempt' => $this->attempts->attemptState($fresh),
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function processEvent(ExaminationAttempt $attempt, array $event): array
    {
        $type = (string) ($event['event_type'] ?? '');
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

        return match ($type) {
            'answer_created', 'answer_updated' => $this->processAnswerEvent($attempt, $payload),
            'violation' => $this->processViolationEvent($attempt, $payload),
            'policy_acceptance' => $this->processPolicyEvent($attempt, $payload),
            'examination_started' => $this->processExaminationStartedEvent($attempt, $payload),
            'examination_submission' => $this->processSubmissionEvent($attempt, $payload),
            'progress_update' => $this->processProgressEvent($attempt, $payload),
            default => throw new InvalidArgumentException("Unsupported sync event type: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function processAnswerEvent(ExaminationAttempt $attempt, array $payload): array
    {
        if ($attempt->status !== AttemptStatus::InProgress && ! $attempt->pending_submission_at) {
            if ($attempt->status->isTerminal()) {
                return [
                    'question_id' => (int) ($payload['question_id'] ?? 0),
                    'saved' => false,
                    'skipped' => true,
                    'reason' => 'attempt_completed',
                ];
            }

            throw new InvalidArgumentException('Attempt is not writable.');
        }

        $questionId = (int) ($payload['question_id'] ?? 0);
        $clientRevision = (string) ($payload['client_revision'] ?? '');
        $answer = $payload['answer'] ?? null;
        $isFlagged = (bool) ($payload['is_flagged'] ?? false);

        if ($questionId < 1) {
            throw new InvalidArgumentException('Invalid question ID.');
        }

        $existing = StudentAnswer::query()
            ->where('examination_attempt_id', $attempt->id)
            ->where('question_id', $questionId)
            ->first();

        if ($existing && $clientRevision !== '') {
            $serverRevision = (string) ($existing->answer['_client_revision'] ?? $existing->updated_at?->timestamp ?? '');
            if ($serverRevision !== '' && $clientRevision < $serverRevision) {
                throw new SyncConflictException(
                    'A newer answer revision exists on the server.',
                    ['question_id' => $questionId, 'server_revision' => $serverRevision],
                );
            }
        }

        $stored = $this->attempts->saveAnswer($attempt, $questionId, $answer, $isFlagged);

        if ($clientRevision !== '') {
            $answerData = $stored->answer;
            $answerData['_client_revision'] = $clientRevision;
            $stored->update(['answer' => $answerData]);
        }

        return [
            'question_id' => $questionId,
            'saved' => true,
            'revision' => $clientRevision ?: now()->timestamp,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function processViolationEvent(ExaminationAttempt $attempt, array $payload): array
    {
        $type = ViolationType::from((string) ($payload['violation_type'] ?? ViolationType::TabOrWindowSwitch->value));

        $result = $this->violations->recordViolation(
            $attempt->fresh(),
            $type,
            $payload['client_event_id'] ?? null,
            [
                'pending_answers' => $payload['pending_answers'] ?? [],
                'offline' => true,
            ],
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function processPolicyEvent(ExaminationAttempt $attempt, array $payload): array
    {
        if ($attempt->policy_accepted_at) {
            return ['accepted' => true, 'duplicate' => true];
        }

        $policyVersion = (string) ($payload['policy_version'] ?? config('examination.policy_version', '1.0'));

        $attempt->update([
            'policy_accepted_at' => now(),
            'policy_version' => $policyVersion,
        ]);

        return ['accepted' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function processExaminationStartedEvent(ExaminationAttempt $attempt, array $payload): array
    {
        if ($attempt->status === AttemptStatus::InProgress) {
            return ['started' => true, 'duplicate' => true];
        }

        if (! $attempt->policy_accepted_at) {
            throw new InvalidArgumentException('Policy must be accepted before starting the examination.');
        }

        if ($attempt->status->isTerminal() || $attempt->status === AttemptStatus::LockedViolationLimit) {
            throw new InvalidArgumentException('This examination attempt can no longer be started.');
        }

        $examination = $attempt->examination()->firstOrFail();
        $durationMinutes = max(1, (int) ($examination->duration_minutes ?: config('examination.default_duration_minutes', 60)));

        $attempt->update([
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($durationMinutes),
            'duration_seconds' => $durationMinutes * 60,
        ]);

        if ($attempt->offline_enabled) {
            $attempt->update([
                'offline_timing_token' => $this->offlinePrep->issueTimingToken($attempt->fresh()),
            ]);
        }

        return ['started' => true, 'status' => AttemptStatus::InProgress->value];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function processProgressEvent(ExaminationAttempt $attempt, array $payload): array
    {
        app(ExamAttemptProgressService::class)->recordProgress(
            $attempt,
            isset($payload['current_question_index']) ? (int) $payload['current_question_index'] : null,
            isset($payload['connection_status']) ? (string) $payload['connection_status'] : 'offline',
        );

        if ($attempt->reactivation_pending && $attempt->reactivated_at) {
            $attempt->update(['reactivation_pending' => false]);
        }

        return [
            'recorded' => true,
            'remaining_seconds' => $attempt->fresh()->remainingSeconds(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function processSubmissionEvent(ExaminationAttempt $attempt, array $payload): array
    {
        if (! empty($payload['answers'])) {
            $this->attempts->saveAnswersBulk($attempt, $payload['answers']);
        }

        if ($attempt->status->isTerminal()) {
            return ['submitted' => true, 'status' => $attempt->status->value];
        }

        if ($attempt->status === AttemptStatus::LockedViolationLimit) {
            throw new InvalidArgumentException('Locked attempts cannot be submitted.');
        }

        $this->validateTimingOnSubmit($attempt, $payload);

        $auto = (bool) ($payload['auto'] ?? false);
        $submitted = $this->attempts->submitAttempt($attempt->fresh(), $auto);

        $submitted->update(['pending_submission_at' => null]);

        return [
            'submitted' => true,
            'status' => $submitted->status->value,
            'result_url' => route('examinations.result', $submitted->examination),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function validateTimingOnSubmit(ExaminationAttempt $attempt, array $payload): void
    {
        $token = (string) ($payload['timing_token'] ?? $attempt->offline_timing_token ?? '');
        if ($token === '') {
            return;
        }

        $timing = $this->offlinePrep->verifyTimingToken($token);
        if (! $timing) {
            throw new InvalidArgumentException('Invalid examination timing authorization.');
        }

        if ($attempt->expires_at && now()->greaterThan($attempt->expires_at)) {
            throw new InvalidArgumentException('Examination time has expired.');
        }
    }

    public function submitOffline(ExaminationAttempt $attempt, array $payload, string $deviceIdentifier): array
    {
        $this->offlinePrep->assertDeviceAuthorized($attempt, $deviceIdentifier);

        $settings = $attempt->examination->settings;
        if (! ($settings?->allow_pending_offline_submission ?? true)) {
            throw new InvalidArgumentException('Pending offline submission is not allowed for this examination.');
        }

        $attempt->update(['pending_submission_at' => now()]);

        $uuid = (string) ($payload['client_event_uuid'] ?? '');
        if ($uuid !== '') {
            return $this->syncEvents($attempt, [[
                'client_event_uuid' => $uuid,
                'event_type' => 'examination_submission',
                'payload' => $payload,
            ]], $deviceIdentifier);
        }

        return [
            'pending' => true,
            'attempt' => $this->attempts->attemptState($attempt->fresh()),
        ];
    }
}
