<?php

namespace App\Services\Examinations;

use App\Enums\AttemptStatus;
use App\Models\AttemptQuestionSnapshot;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExaminationVersion;
use App\Models\ExamPolicyAcceptance;
use App\Models\Student;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\Grading\GradingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExaminationAttemptService
{
    public function __construct(
        protected ExaminationAccessService $access,
        protected GradingEngine $grading,
        protected ?OfflineExamPreparationService $offlinePrep = null,
    ) {}

    public function findResumableAttempt(Student $student, Examination $examination): ?ExaminationAttempt
    {
        return ExaminationAttempt::query()
            ->where('examination_id', $examination->id)
            ->where('student_id', $student->id)
            ->whereIn('status', [
                AttemptStatus::NotStarted,
                AttemptStatus::InProgress,
                AttemptStatus::LockedViolationLimit,
            ])
            ->latest('id')
            ->first();
    }

    public function acceptPolicy(User $user, Examination $examination): ExaminationAttempt
    {
        $student = $this->requireStudent($user);

        if (! $this->access->canTake($user, $examination)) {
            throw new InvalidArgumentException($this->access->denyTakeReason($user, $examination));
        }

        $policyVersion = (string) config('examination.policy_version', '1.0');

        return DB::transaction(function () use ($student, $examination, $policyVersion) {
            $attempt = $this->findResumableAttempt($student, $examination);

            if ($attempt && $attempt->policy_accepted_at) {
                return $attempt->fresh();
            }

            if (! $attempt) {
                $attempt = $this->createAttempt($student, $examination);
            }

            if ($attempt->status->isTerminal()) {
                throw new InvalidArgumentException('This examination attempt can no longer be started.');
            }

            $attempt->update([
                'policy_accepted_at' => now(),
                'policy_version' => $policyVersion,
            ]);

            ExamPolicyAcceptance::create([
                'student_id' => $student->id,
                'examination_id' => $examination->id,
                'examination_attempt_id' => $attempt->id,
                'policy_version' => $policyVersion,
                'accepted_at' => now(),
                'ip_address' => request()->ip(),
            ]);

            return $attempt->fresh();
        });
    }

    public function startAttempt(User $user, Examination $examination): ExaminationAttempt
    {
        $student = $this->requireStudent($user);

        return DB::transaction(function () use ($user, $student, $examination) {
            $attempt = $this->findResumableAttempt($student, $examination);

            if (! $attempt || ! $attempt->policy_accepted_at) {
                throw new InvalidArgumentException('You must accept the examination policy before starting.');
            }

            if ($attempt->status === AttemptStatus::LockedViolationLimit) {
                throw new InvalidArgumentException('Your examination is locked. Please approach your instructor.');
            }

            if ($attempt->status->isTerminal()) {
                throw new InvalidArgumentException('This examination attempt has already been completed.');
            }

            if ($attempt->status === AttemptStatus::InProgress) {
                return $attempt->fresh(['answers']);
            }

            $durationMinutes = max(1, (int) ($examination->duration_minutes ?: config('examination.default_duration_minutes', 60)));

            $attempt->update([
                'status' => AttemptStatus::InProgress,
                'started_at' => now(),
                'expires_at' => now()->addMinutes($durationMinutes),
                'duration_seconds' => $durationMinutes * 60,
                'ip_address' => request()->ip(),
            ]);

            $this->ensureSnapshots($attempt, $examination);

            $attempt = $attempt->fresh(['answers']);

            if ($attempt->offline_enabled) {
                $offlinePrep = $this->offlinePrep ?? app(OfflineExamPreparationService::class);
                $attempt->update([
                    'offline_timing_token' => $offlinePrep->issueTimingToken($attempt),
                ]);
            }

            return $attempt->fresh(['answers']);
        });
    }

    public function saveAnswer(ExaminationAttempt $attempt, int $questionId, mixed $answer, bool $isFlagged = false): StudentAnswer
    {
        $this->assertAttemptWritable($attempt);

        $record = StudentAnswer::firstOrNew([
            'examination_attempt_id' => $attempt->id,
            'question_id' => $questionId,
        ]);

        if (! $record->exists) {
            $record->uuid = (string) Str::uuid();
        }

        $record->fill([
            'answer' => is_array($answer) ? $answer : ['value' => $answer],
            'is_flagged' => $isFlagged,
            'answered_at' => now(),
        ])->save();

        return $record;
    }

    /**
     * @param  array<int, array{question_id: int, answer: mixed, is_flagged?: bool}>  $answers
     */
    public function saveAnswersBulk(ExaminationAttempt $attempt, array $answers): void
    {
        if ($answers === []) {
            return;
        }

        $this->assertAttemptWritable($attempt);

        DB::transaction(function () use ($attempt, $answers) {
            foreach ($answers as $item) {
                $questionId = (int) ($item['question_id'] ?? 0);
                if ($questionId < 1) {
                    continue;
                }

                $this->saveAnswer(
                    $attempt,
                    $questionId,
                    $item['answer'] ?? null,
                    (bool) ($item['is_flagged'] ?? false),
                );
            }
        });
    }

    public function submitAttempt(ExaminationAttempt $attempt, bool $auto = false): ExaminationAttempt
    {
        if ($attempt->status === AttemptStatus::LockedViolationLimit) {
            throw new InvalidArgumentException('Your examination is locked and cannot be submitted.');
        }

        if ($attempt->status->isTerminal()) {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt, $auto) {
            $attempt->update([
                'status' => $auto ? AttemptStatus::AutoSubmitted : AttemptStatus::Submitted,
                'submitted_at' => now(),
            ]);

            $this->grading->applyToAttempt($attempt->fresh());

            return $attempt->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function attemptState(ExaminationAttempt $attempt): array
    {
        $attempt->loadMissing(['answers', 'violations' => fn ($q) => $q->latest('detected_at')]);

        return [
            'attempt_id' => $attempt->id,
            'status' => $attempt->status->value,
            'policy_accepted' => $attempt->policy_accepted_at !== null,
            'policy_version' => $attempt->policy_version,
            'warning_count' => (int) $attempt->warning_count,
            'max_warnings' => $attempt->maxWarnings(),
            'remaining_seconds' => $attempt->remainingSeconds(),
            'duration_seconds' => max(1, (int) $attempt->duration_seconds),
            'locked_at' => $attempt->locked_at?->toIso8601String(),
            'lock_reason' => $attempt->lock_reason,
            'reactivated_at' => $attempt->reactivated_at?->toIso8601String(),
            'offline_enabled' => (bool) $attempt->offline_enabled,
            'offline_prepared_at' => $attempt->offline_prepared_at?->toIso8601String(),
            'offline_session_id' => $attempt->offline_session_id,
            'authorized_device_id' => $attempt->authorized_device_id,
            'last_synced_at' => $attempt->last_synced_at?->toIso8601String(),
            'pending_submission_at' => $attempt->pending_submission_at?->toIso8601String(),
            'offline_timing_token' => $attempt->offline_timing_token,
            'answers' => $attempt->answers->mapWithKeys(function (StudentAnswer $answer) {
                $value = $answer->answer['value'] ?? $answer->answer;

                return [$answer->question_id => [
                    'answer' => $value,
                    'is_flagged' => $answer->is_flagged,
                ]];
            })->all(),
            'latest_violation' => $attempt->violations->first()?->only([
                'violation_type', 'warning_number', 'detected_at',
            ]),
        ];
    }

    protected function createAttempt(Student $student, Examination $examination): ExaminationAttempt
    {
        $attemptNumber = ExaminationAttempt::query()
            ->where('examination_id', $examination->id)
            ->where('student_id', $student->id)
            ->max('attempt_number');

        $version = $this->resolveVersion($examination);

        return ExaminationAttempt::create([
            'uuid' => (string) Str::uuid(),
            'examination_id' => $examination->id,
            'examination_version_id' => $version->id,
            'student_id' => $student->id,
            'attempt_number' => ((int) $attemptNumber) + 1,
            'status' => AttemptStatus::NotStarted,
        ]);
    }

    protected function resolveVersion(Examination $examination): ExaminationVersion
    {
        $version = $examination->versions()
            ->where('version_number', $examination->current_version ?: 1)
            ->first();

        if ($version) {
            return $version;
        }

        return ExaminationVersion::create([
            'examination_id' => $examination->id,
            'version_number' => $examination->current_version ?: 1,
        ]);
    }

    protected function ensureSnapshots(ExaminationAttempt $attempt, Examination $examination): void
    {
        if ($attempt->snapshots()->exists()) {
            return;
        }

        $questions = $examination->examQuestions()->with('question.choices')->orderBy('order')->get();

        if ($questions->isEmpty()) {
            return;
        }

        $order = [];
        foreach ($questions as $index => $examQuestion) {
            $question = $examQuestion->question;
            if (! $question) {
                continue;
            }

            $choiceOrder = $question->choices->pluck('id')->all();

            AttemptQuestionSnapshot::create([
                'examination_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'display_order' => $index + 1,
                'question_snapshot' => [
                    'id' => $question->id,
                    'text' => $question->question_text,
                    'type' => $question->type->value ?? (string) $question->type,
                    'choices' => $question->choices->map(fn ($choice) => [
                        'id' => strtoupper((string) $choice->label),
                        'text' => $choice->choice_text,
                    ])->all(),
                ],
                'choice_order' => $choiceOrder,
                'points' => $examQuestion->points_override ?? $question->points,
            ]);

            $order[] = $question->id;
        }

        $attempt->update(['question_order' => $order]);
    }

    protected function assertAttemptWritable(ExaminationAttempt $attempt): void
    {
        if ($attempt->status !== AttemptStatus::InProgress) {
            throw new InvalidArgumentException('This examination attempt is not active.');
        }

        if ($attempt->expires_at && now()->greaterThan($attempt->expires_at)) {
            throw new InvalidArgumentException('Your examination time has expired.');
        }
    }

    protected function requireStudent(User $user): Student
    {
        $student = $user->student;

        if (! $student) {
            throw new InvalidArgumentException('Only students can take examinations.');
        }

        return $student;
    }
}
