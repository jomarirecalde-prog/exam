<?php

namespace App\Services\Examinations;

use App\Enums\AttemptStatus;
use App\Enums\OfflineExaminationMode;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\OfflineExamDevice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OfflineExamPreparationService
{
    public function __construct(
        protected ExaminationAccessService $access,
        protected ExaminationAttemptService $attempts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function prepare(User $user, Examination $examination, string $deviceIdentifier, ?string $deviceName = null): array
    {
        $student = $this->requireStudent($user);
        $examination->loadMissing(['settings', 'subject']);

        $this->assertEligible($user, $student, $examination);

        $settings = $examination->settings;
        $mode = $settings?->offline_examination_mode ?? OfflineExaminationMode::Disabled;

        if (! $mode->supportsOffline()) {
            throw new InvalidArgumentException('This examination does not support offline mode.');
        }

        if (! ($settings?->allow_offline_continuation ?? false)) {
            throw new InvalidArgumentException('Offline continuation is not enabled for this examination.');
        }

        return DB::transaction(function () use ($user, $student, $examination, $deviceIdentifier, $deviceName, $settings) {
            $attempt = $this->attempts->findResumableAttempt($student, $examination);

            if (! $attempt) {
                throw new InvalidArgumentException('Accept the examination policy before preparing offline mode.');
            }

            if ($attempt->status->isTerminal()) {
                throw new InvalidArgumentException('This examination attempt has already been completed.');
            }

            if ($attempt->authorized_device_id
                && $attempt->authorized_device_id !== $deviceIdentifier
                && $attempt->status === AttemptStatus::InProgress) {
                throw new InvalidArgumentException('This examination attempt is already active on another authorized device.');
            }

            $this->registerDevice($student, $deviceIdentifier, $deviceName);

            $sessionId = (string) Str::uuid();
            $package = $this->buildPackage($examination, $attempt, $settings);

            $attempt->update([
                'offline_enabled' => true,
                'offline_prepared_at' => now(),
                'offline_session_id' => $sessionId,
                'authorized_device_id' => $deviceIdentifier,
                'offline_timing_token' => $this->issueTimingToken($attempt),
            ]);

            return [
                'prepared' => true,
                'offline_session_id' => $sessionId,
                'authorization_expires_at' => now()->addHours((int) config('examination.offline_session_hours', 168))->toIso8601String(),
                'attempt' => $this->attempts->attemptState($attempt->fresh()),
                'package' => $package,
                'offline' => $this->offlineMeta($examination, $settings),
            ];
        });
    }

    protected function assertEligible(User $user, Student $student, Examination $examination): void
    {
        if (! $this->access->canTake($user, $examination)) {
            throw new InvalidArgumentException($this->access->denyTakeReason($user, $examination));
        }

        if ($this->access->hasExceededAttempts($student, $examination)) {
            throw new InvalidArgumentException('You have exceeded the allowed number of attempts for this examination.');
        }
    }

    protected function registerDevice(Student $student, string $deviceIdentifier, ?string $deviceName): OfflineExamDevice
    {
        return OfflineExamDevice::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'device_identifier' => $deviceIdentifier,
            ],
            [
                'device_name' => $deviceName,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPackage(Examination $examination, ExaminationAttempt $attempt, $settings): array
    {
        $attached = $examination->examQuestions()->with('question.choices')->orderBy('order')->get();

        $questions = $attached->map(function ($item) {
            $question = $item->question;
            if (! $question) {
                return null;
            }

            return [
                'id' => $question->id,
                'text' => $question->question_text,
                'type' => $question->type->value ?? (string) $question->type,
                'points' => $item->points_override ?? $question->points,
                'choices' => $question->choices->map(fn ($choice) => [
                    'id' => strtoupper((string) $choice->label),
                    'text' => $choice->choice_text,
                ])->all(),
                'media_urls' => [],
            ];
        })->filter()->values()->all();

        $durationMinutes = max(1, (int) ($examination->duration_minutes ?: config('examination.default_duration_minutes', 60)));
        $ttlHours = (int) config('examination.offline_session_hours', 168);
        $authorizationExpires = now()->addHours($ttlHours);

        return [
            'examination_id' => $examination->id,
            'attempt_id' => $attempt->id,
            'student_id' => $attempt->student_id,
            'title' => $examination->title,
            'subject_code' => $examination->subject?->code,
            'subject_name' => $examination->subject?->name,
            'instructions' => $examination->instructions,
            'duration_minutes' => $durationMinutes,
            'policy_version' => (string) config('examination.policy_version', '1.0'),
            'max_warnings' => (int) config('examination.max_violation_warnings', 3),
            'questions' => $questions,
            'monitoring' => [
                'requireFullscreen' => (bool) ($settings?->require_fullscreen ?? true),
                'detectTabSwitch' => (bool) ($settings?->detect_tab_switch ?? true),
                'disableCopyPaste' => (bool) ($settings?->disable_copy_paste ?? true),
                'disableRightClick' => (bool) ($settings?->disable_right_click ?? true),
            ],
            'prepared_at' => now()->toIso8601String(),
            'authorization_expires_at' => $authorizationExpires->toIso8601String(),
            'take_url' => route('offline.examinations.take', $examination),
            'offline_session_id' => $attempt->offline_session_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function offlineMeta(Examination $examination, $settings): array
    {
        $mode = $settings?->offline_examination_mode ?? OfflineExaminationMode::Disabled;

        return [
            'mode' => $mode->value,
            'mode_label' => $mode->label(),
            'require_preparation' => (bool) ($settings?->require_offline_preparation ?? false),
            'allow_pending_submission' => (bool) ($settings?->allow_pending_offline_submission ?? true),
            'max_offline_duration_minutes' => $settings?->max_offline_duration_minutes,
            'sync_grace_period_minutes' => (int) ($settings?->sync_grace_period_minutes ?? 15),
            'authorization_expires_at' => now()->addHours((int) config('examination.offline_session_hours', 168))->toIso8601String(),
        ];
    }

    public function issueTimingToken(ExaminationAttempt $attempt): string
    {
        $payload = [
            'attempt_id' => $attempt->id,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'duration_seconds' => (int) $attempt->duration_seconds,
            'issued_at' => now()->toIso8601String(),
        ];

        $encoded = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encoded, (string) config('app.key'));

        return $encoded.'.'.$signature;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyTimingToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;
        $expected = hash_hmac('sha256', $encoded, (string) config('app.key'));

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(base64_decode($encoded, true) ?: '', true);

        return is_array($payload) ? $payload : null;
    }

    public function assertDeviceAuthorized(ExaminationAttempt $attempt, string $deviceIdentifier): void
    {
        if ($attempt->authorized_device_id && $attempt->authorized_device_id !== $deviceIdentifier) {
            throw new InvalidArgumentException('This examination attempt is authorized for a different device.');
        }
    }

    protected function requireStudent(User $user): Student
    {
        $student = $user->student;

        if (! $student) {
            throw new InvalidArgumentException('Only students can prepare examinations for offline use.');
        }

        return $student;
    }
}
