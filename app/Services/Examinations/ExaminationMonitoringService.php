<?php

namespace App\Services\Examinations;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ReactivationWarningMode;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExamReactivationLog;
use App\Models\ExamSyncEvent;
use App\Models\ExamViolation;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExaminationMonitoringService
{
    public function __construct(
        protected ExaminationAccessService $access,
        protected OfflineExamPreparationService $offlinePrep,
        protected ExamAttemptProgressService $progress,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Examination $examination, User $user, ?Carbon $since = null): array
    {
        $this->assertCanMonitor($user, $examination);

        $examination->loadMissing(['subject', 'sections', 'subjectOffering.section', 'endedBy']);

        $totalQuestions = $this->resolveExamQuestionCount($examination);
        $students = $this->buildStudentRows($examination, $totalQuestions);
        $summary = $this->buildSummary($students);
        $activities = $this->activityFeed($examination, $user, 50);

        if ($since !== null) {
            $students = $students->filter(function (array $row) use ($since) {
                if (empty($row['updated_at'])) {
                    return false;
                }

                return Carbon::parse($row['updated_at'])->gt($since);
            })->values();

            $activities = collect($activities)->filter(function (array $item) use ($since) {
                return Carbon::parse($item['occurred_at'])->gt($since);
            })->values()->all();
        }

        return [
            'examination' => $this->formatExamination($examination, $students, $summary),
            'summary' => $summary,
            'students' => $students->values()->all(),
            'activities' => $activities,
            'server_time' => now()->toIso8601String(),
            'total_questions' => $totalQuestions,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function attemptsForExamination(Examination $examination, User $user): Collection
    {
        return collect($this->dashboard($examination, $user)['students']);
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

    /**
     * @return array<string, mixed>
     */
    public function attemptDetail(ExaminationAttempt $attempt, User $user): array
    {
        $this->assertCanMonitor($user, $attempt->examination);

        $attempt->loadMissing(['student.user', 'student.section', 'student.program', 'examination.subject', 'violations']);
        $totalQuestions = $this->progress->totalQuestions($attempt);

        return $this->formatAttemptRow($attempt, $attempt->examination, $totalQuestions, detailed: true);
    }

    public function reactivate(
        ExaminationAttempt $attempt,
        User $user,
        string $reason,
        ReactivationWarningMode $warningMode,
        ?int $manualWarningCount = null,
    ): ExaminationAttempt {
        $this->assertCanMonitor($user, $attempt->examination);

        if (! $attempt->canBeReactivated()) {
            throw new InvalidArgumentException('Only locked examination attempts can be reactivated.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reactivation reason is required.');
        }

        return DB::transaction(function () use ($attempt, $user, $reason, $warningMode, $manualWarningCount) {
            $attempt = ExaminationAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if (! $attempt->canBeReactivated()) {
                throw new InvalidArgumentException('Only locked examination attempts can be reactivated.');
            }

            $previousWarnings = (int) $attempt->warning_count;
            $maxWarnings = $attempt->maxWarnings();

            $newWarnings = match ($warningMode) {
                ReactivationWarningMode::Reset => 0,
                ReactivationWarningMode::Keep => $previousWarnings,
                ReactivationWarningMode::Manual => max(0, min($maxWarnings, (int) $manualWarningCount)),
            };

            $timerReset = $this->timerResetAttributes($attempt);
            $studentOffline = $this->isStudentOffline($attempt);

            $updates = [
                'status' => AttemptStatus::InProgress,
                'warning_count' => $newWarnings,
                'locked_at' => null,
                'lock_reason' => null,
                'reactivated_at' => now(),
                'reactivated_by' => $user->id,
                'reactivation_reason' => $reason,
                'reactivation_count' => (int) $attempt->reactivation_count + 1,
                'reactivation_pending' => $studentOffline,
                'last_activity_at' => now(),
                ...$timerReset,
            ];

            if ($attempt->offline_enabled) {
                $attempt->fill($timerReset);
                $updates['offline_timing_token'] = $this->offlinePrep->issueTimingToken($attempt);
            }

            $attempt->update($updates);

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
     * @return array<int, array<string, mixed>>
     */
    public function activityFeed(Examination $examination, User $user, int $limit = 50): array
    {
        $this->assertCanMonitor($user, $examination);

        $attemptIds = ExaminationAttempt::query()
            ->where('examination_id', $examination->id)
            ->pluck('id');

        if ($attemptIds->isEmpty()) {
            return [];
        }

        $violations = ExamViolation::query()
            ->with(['student.user'])
            ->whereIn('examination_attempt_id', $attemptIds)
            ->latest('detected_at')
            ->limit($limit)
            ->get()
            ->map(fn (ExamViolation $violation) => [
                'id' => 'violation-'.$violation->id,
                'type' => 'violation',
                'severity' => $violation->warning_number >= (int) config('examination.max_violation_warnings', 3) ? 'critical' : 'warning',
                'student_name' => $violation->student?->displayName() ?? 'Student',
                'message' => sprintf(
                    'received Warning %d (%s)',
                    $violation->warning_number,
                    $violation->violation_type->label(),
                ),
                'occurred_at' => $violation->detected_at->toIso8601String(),
                'occurred_at_label' => $violation->detected_at->format('g:i:s A'),
            ]);

        $syncEvents = ExamSyncEvent::query()
            ->with(['attempt.student.user'])
            ->whereIn('examination_attempt_id', $attemptIds)
            ->whereIn('event_type', [
                'examination_started',
                'examination_submission',
                'answer_updated',
                'answer_created',
                'violation',
                'progress_update',
            ])
            ->latest('processed_at')
            ->limit($limit)
            ->get()
            ->map(function (ExamSyncEvent $event) {
                $student = $event->attempt?->student;
                $payload = is_array($event->payload) ? $event->payload : [];

                $message = match ($event->event_type) {
                    'examination_started' => 'started the examination',
                    'examination_submission' => 'submitted the examination',
                    'answer_updated', 'answer_created' => isset($payload['question_id'])
                        ? 'answered Question '.((int) ($payload['question_index'] ?? 0) ?: '?')
                        : 'updated an answer',
                    'violation' => 'recorded a violation (offline sync)',
                    'progress_update' => 'updated examination progress',
                    default => 'had examination activity',
                };

                return [
                    'id' => 'sync-'.$event->id,
                    'type' => $event->event_type,
                    'severity' => in_array($event->event_type, ['examination_submission', 'violation'], true) ? 'info' : 'normal',
                    'student_name' => $student?->displayName() ?? 'Student',
                    'message' => $message,
                    'occurred_at' => ($event->processed_at ?? now())->toIso8601String(),
                    'occurred_at_label' => ($event->processed_at ?? now())->format('g:i:s A'),
                ];
            });

        $attemptEvents = ExaminationAttempt::query()
            ->with(['student.user'])
            ->where('examination_id', $examination->id)
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->limit($limit)
            ->get()
            ->map(fn (ExaminationAttempt $attempt) => [
                'id' => 'submit-'.$attempt->id,
                'type' => 'submitted',
                'severity' => 'info',
                'student_name' => $attempt->student?->displayName() ?? 'Student',
                'message' => 'submitted the examination',
                'occurred_at' => $attempt->submitted_at?->toIso8601String(),
                'occurred_at_label' => $attempt->submitted_at?->format('g:i:s A'),
            ]);

        $endEvents = collect();

        if ($examination->ended_at) {
            $endEvents->push([
                'id' => 'exam-ended-'.$examination->id,
                'type' => 'examination_ended',
                'severity' => 'critical',
                'student_name' => $examination->endedBy?->name ?? 'Instructor',
                'message' => 'ended the examination'.($examination->end_reason ? ': '.$examination->end_reason : ''),
                'occurred_at' => $examination->ended_at->toIso8601String(),
                'occurred_at_label' => $examination->ended_at->format('g:i A'),
            ]);
        }

        return $violations
            ->concat($syncEvents)
            ->concat($attemptEvents)
            ->concat($endEvents)
            ->filter(fn (array $item) => ! empty($item['occurred_at']))
            ->sortByDesc('occurred_at')
            ->unique('id')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildStudentRows(Examination $examination, int $totalQuestions): Collection
    {
        $eligibleStudents = $this->access->eligibleStudents($examination);

        $attempts = ExaminationAttempt::query()
            ->with([
                'student.user',
                'student.section',
                'student.program',
                'violations' => fn ($q) => $q->latest('detected_at')->limit(1),
                'answers',
                'snapshots',
            ])
            ->where('examination_id', $examination->id)
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $group) => $group->sortByDesc('id')->first());

        return $eligibleStudents->map(function (Student $student) use ($examination, $attempts, $totalQuestions) {
            $attempt = $attempts->get($student->id);

            if ($attempt) {
                return $this->formatAttemptRow($attempt, $examination, $totalQuestions, $student);
            }

            return $this->formatNotStartedRow($student, $examination, $totalQuestions);
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $students
     * @return array<string, int>
     */
    protected function buildSummary(Collection $students): array
    {
        $counts = [
            'total' => $students->count(),
            'taking_exam' => 0,
            'not_started' => 0,
            'submitted' => 0,
            'offline' => 0,
            'locked' => 0,
            'pending_submission' => 0,
            'preparing' => 0,
            'expired' => 0,
        ];

        foreach ($students as $row) {
            match ($row['monitoring_status']) {
                'TAKING_EXAM' => $counts['taking_exam']++,
                'NOT_STARTED' => $counts['not_started']++,
                'SUBMITTED' => $counts['submitted']++,
                'OFFLINE' => $counts['offline']++,
                'LOCKED' => $counts['locked']++,
                'PENDING_SUBMISSION' => $counts['pending_submission']++,
                'PREPARING' => $counts['preparing']++,
                'EXPIRED' => $counts['expired']++,
                default => null,
            };
        }

        return $counts;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $students
     * @param  array<string, int>  $summary
     * @return array<string, mixed>
     */
    protected function formatExamination(Examination $examination, Collection $students, array $summary): array
    {
        $sections = $examination->sections->pluck('name')->filter()->join(', ')
            ?: $examination->subjectOffering?->section?->name
            ?: 'Unassigned';

        $isLive = in_array($examination->status, [ExamStatus::Active, ExamStatus::Published, ExamStatus::Scheduled], true);

        $schedule = app(ExaminationScheduleService::class);

        return [
            'id' => $examination->id,
            'title' => $examination->title,
            'subject' => $examination->subject?->name ?? $examination->subject?->code,
            'subject_code' => $examination->subject?->code,
            'sections' => $sections,
            'status' => $examination->status->value,
            'status_label' => $examination->status->label(),
            'is_live' => $isLive,
            'students_taking' => $summary['taking_exam'] + $summary['offline'],
            'students_total' => $summary['total'],
            'duration_minutes' => (int) ($examination->duration_minutes ?: config('examination.default_duration_minutes', 60)),
            ...$schedule->schedulePayload($examination),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatNotStartedRow(Student $student, Examination $examination, int $totalQuestions): array
    {
        return [
            'attempt_id' => null,
            'student_id' => $student->student_id,
            'student_db_id' => $student->id,
            'student_name' => $student->displayName(),
            'section' => $student->section?->name ?? '—',
            'program' => $student->program?->name ?? '—',
            'monitoring_status' => 'NOT_STARTED',
            'status' => AttemptStatus::NotStarted->value,
            'status_label' => 'Not Started',
            'status_filter' => 'not_started',
            'progress_percent' => 0,
            'answered_count' => 0,
            'total_questions' => $totalQuestions,
            'current_question' => null,
            'current_question_label' => '—',
            'remaining_seconds' => null,
            'remaining_time_label' => '—',
            'warning_count' => 0,
            'max_warnings' => (int) config('examination.max_violation_warnings', 3),
            'latest_violation' => null,
            'latest_violation_at' => null,
            'connection_status' => 'unknown',
            'connection_label' => '—',
            'connection_detail' => null,
            'last_activity_at' => null,
            'last_activity_label' => '—',
            'last_synced_at' => null,
            'last_synced_label' => null,
            'can_reactivate' => false,
            'reactivation_pending' => false,
            'lock_reason' => null,
            'started_at' => null,
            'submitted_at' => null,
            'offline_enabled' => false,
            'priority' => 60,
            'updated_at' => null,
            'examination' => $examination->title,
            'subject_offering' => $examination->subject?->code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatAttemptRow(
        ExaminationAttempt $attempt,
        Examination $examination,
        int $totalQuestions,
        ?Student $student = null,
        bool $detailed = false,
    ): array {
        $student ??= $attempt->student;
        $latestViolation = $attempt->violations->first();
        $attemptTotal = max($totalQuestions, $this->progress->totalQuestions($attempt));
        $answeredCount = $this->progress->countAnsweredQuestions($attempt);
        $progressPercent = $attemptTotal > 0 ? (int) round(($answeredCount / $attemptTotal) * 100) : 0;
        $monitoringStatus = $this->resolveMonitoringStatus($attempt, $examination);
        $connection = $this->resolveConnectionStatus($attempt, $monitoringStatus);
        $currentIndex = (int) ($attempt->current_question_index ?: 0);
        $remainingSeconds = in_array($monitoringStatus, ['SUBMITTED', 'EXPIRED', 'LOCKED'], true)
            ? null
            : $attempt->remainingSeconds();

        if ($monitoringStatus === 'SUBMITTED') {
            $progressPercent = 100;
            $answeredCount = $attemptTotal;
        }

        $row = [
            'attempt_id' => $attempt->id,
            'student_id' => $student?->student_id,
            'student_db_id' => $student?->id,
            'student_name' => $student?->displayName() ?? '—',
            'section' => $student?->section?->name ?? '—',
            'program' => $student?->program?->name ?? '—',
            'monitoring_status' => $monitoringStatus,
            'status' => $attempt->status->value,
            'status_label' => $this->statusLabel($monitoringStatus, $attempt),
            'status_filter' => $this->statusFilter($monitoringStatus),
            'progress_percent' => $progressPercent,
            'answered_count' => $answeredCount,
            'total_questions' => $attemptTotal,
            'current_question' => $currentIndex > 0 ? $currentIndex : null,
            'current_question_label' => $this->currentQuestionLabel($monitoringStatus, $currentIndex, $attemptTotal),
            'remaining_seconds' => $remainingSeconds,
            'remaining_time_label' => $remainingSeconds !== null ? $this->formatDuration($remainingSeconds) : '—',
            'warning_count' => (int) $attempt->warning_count,
            'max_warnings' => $attempt->maxWarnings(),
            'latest_violation' => $latestViolation?->violation_type->label(),
            'latest_violation_at' => $latestViolation?->detected_at?->format('M j, g:i A'),
            'connection_status' => $connection['status'],
            'connection_label' => $connection['label'],
            'connection_detail' => $connection['detail'],
            'last_activity_at' => $attempt->last_activity_at?->toIso8601String(),
            'last_activity_label' => $this->relativeTime($attempt->last_activity_at),
            'last_synced_at' => $attempt->last_synced_at?->toIso8601String(),
            'last_synced_label' => $attempt->last_synced_at?->format('g:i:s A'),
            'can_reactivate' => $attempt->canBeReactivated(),
            'reactivation_pending' => (bool) $attempt->reactivation_pending,
            'lock_reason' => $attempt->lock_reason,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'offline_enabled' => (bool) $attempt->offline_enabled,
            'priority' => $this->attentionPriority($monitoringStatus, (int) $attempt->warning_count),
            'updated_at' => $this->rowUpdatedAt($attempt),
            'examination' => $examination->title,
            'subject_offering' => $examination->subject?->code,
        ];

        if ($detailed) {
            $row['duration_minutes'] = max(1, (int) ($examination->duration_minutes ?: config('examination.default_duration_minutes', 60)));
            $row['reactivated_at'] = $attempt->reactivated_at?->toIso8601String();
            $row['reactivation_reason'] = $attempt->reactivation_reason;
        }

        return $row;
    }

    protected function resolveMonitoringStatus(ExaminationAttempt $attempt, Examination $examination): string
    {
        if ($attempt->reactivation_pending) {
            return 'REACTIVATED';
        }

        if ($attempt->status === AttemptStatus::LockedViolationLimit || $attempt->isLockedForViolations()) {
            return 'LOCKED';
        }

        if ($attempt->status === AttemptStatus::SyncPending || $attempt->pending_submission_at) {
            return 'PENDING_SUBMISSION';
        }

        if (in_array($attempt->status, [AttemptStatus::Submitted, AttemptStatus::AutoSubmitted, AttemptStatus::Synced], true)) {
            return 'SUBMITTED';
        }

        if ($attempt->status === AttemptStatus::Expired) {
            return 'EXPIRED';
        }

        if ($attempt->status === AttemptStatus::NotStarted) {
            if ($attempt->offline_prepared_at && ! $attempt->started_at) {
                return 'PREPARING';
            }

            return 'NOT_STARTED';
        }

        if ($examination->status === ExamStatus::Paused) {
            return 'PAUSED';
        }

        if ($attempt->status === AttemptStatus::InProgress && $this->isStudentOffline($attempt)) {
            return 'OFFLINE';
        }

        if ($attempt->status === AttemptStatus::InProgress) {
            return 'TAKING_EXAM';
        }

        return strtoupper((string) $attempt->status->value);
    }

    protected function isStudentOffline(ExaminationAttempt $attempt): bool
    {
        if ($attempt->connection_status === 'offline') {
            return true;
        }

        if ($attempt->offline_enabled && $attempt->status === AttemptStatus::InProgress) {
            $threshold = now()->subSeconds(90);

            if ($attempt->last_activity_at && $attempt->last_activity_at->lt($threshold)) {
                return true;
            }

            if ($attempt->last_synced_at && $attempt->last_synced_at->lt($threshold) && $attempt->connection_status !== 'online') {
                return true;
            }
        }

        if ($attempt->connection_status === 'reconnecting') {
            return true;
        }

        return false;
    }

    /**
     * @return array{status: string, label: string, detail: ?string}
     */
    protected function resolveConnectionStatus(ExaminationAttempt $attempt, string $monitoringStatus): array
    {
        if (in_array($monitoringStatus, ['NOT_STARTED', 'PREPARING'], true)) {
            return ['status' => 'unknown', 'label' => '—', 'detail' => null];
        }

        if ($monitoringStatus === 'SUBMITTED') {
            return ['status' => 'online', 'label' => 'Online', 'detail' => null];
        }

        if ($monitoringStatus === 'OFFLINE' || $this->isStudentOffline($attempt)) {
            $detail = 'Last synchronized: '.($attempt->last_synced_at?->format('g:i:s A') ?? 'Unknown')
                .'. Current information will update when the student reconnects.';

            return ['status' => 'offline', 'label' => 'Offline', 'detail' => $detail];
        }

        if ($attempt->connection_status === 'reconnecting') {
            return ['status' => 'reconnecting', 'label' => 'Reconnecting', 'detail' => 'Attempting to restore connection.'];
        }

        if ($attempt->last_activity_at && $attempt->last_activity_at->lt(now()->subMinutes(2))) {
            return [
                'status' => 'offline',
                'label' => 'Last seen '.$attempt->last_activity_at->diffForHumans(),
                'detail' => 'No recent activity detected.',
            ];
        }

        return ['status' => 'online', 'label' => 'Online', 'detail' => null];
    }

    protected function currentQuestionLabel(string $monitoringStatus, int $currentIndex, int $totalQuestions): string
    {
        if (in_array($monitoringStatus, ['SUBMITTED', 'EXPIRED'], true)) {
            return 'Completed';
        }

        if ($monitoringStatus === 'NOT_STARTED') {
            return '—';
        }

        if ($currentIndex < 1 || $totalQuestions < 1) {
            return '—';
        }

        return "Question {$currentIndex} of {$totalQuestions}";
    }

    protected function statusLabel(string $monitoringStatus, ExaminationAttempt $attempt): string
    {
        return match ($monitoringStatus) {
            'NOT_STARTED' => 'Not Started',
            'PREPARING' => 'Preparing',
            'TAKING_EXAM' => 'Taking Exam',
            'OFFLINE' => 'Offline',
            'PAUSED' => 'Paused',
            'LOCKED' => 'Locked',
            'PENDING_SUBMISSION' => 'Pending Submission',
            'SUBMITTED' => 'Submitted',
            'EXPIRED' => 'Expired',
            'REACTIVATED' => $attempt->reactivation_pending ? 'Reactivation Pending' : 'Taking Exam',
            default => str_replace('_', ' ', $monitoringStatus),
        };
    }

    protected function statusFilter(string $monitoringStatus): string
    {
        return match ($monitoringStatus) {
            'TAKING_EXAM', 'REACTIVATED', 'PAUSED' => 'taking_exam',
            'NOT_STARTED', 'PREPARING' => 'not_started',
            'OFFLINE' => 'offline',
            'LOCKED' => 'locked',
            'SUBMITTED' => 'submitted',
            'PENDING_SUBMISSION' => 'pending_submission',
            default => 'all',
        };
    }

    protected function attentionPriority(string $monitoringStatus, int $warningCount): int
    {
        return match (true) {
            $monitoringStatus === 'LOCKED' => 10,
            $warningCount >= 2 => 20,
            $monitoringStatus === 'OFFLINE' => 30,
            $monitoringStatus === 'PENDING_SUBMISSION' => 40,
            $monitoringStatus === 'TAKING_EXAM', $monitoringStatus === 'REACTIVATED' => 50,
            $monitoringStatus === 'NOT_STARTED', $monitoringStatus === 'PREPARING' => 60,
            $monitoringStatus === 'SUBMITTED' => 70,
            default => 80,
        };
    }

    protected function rowUpdatedAt(ExaminationAttempt $attempt): ?string
    {
        $timestamps = array_filter([
            $attempt->updated_at,
            $attempt->last_activity_at,
            $attempt->last_synced_at,
            $attempt->submitted_at,
            $attempt->locked_at,
            $attempt->reactivated_at,
        ]);

        if ($timestamps === []) {
            return null;
        }

        return collect($timestamps)->max()->toIso8601String();
    }

    protected function resolveExamQuestionCount(Examination $examination): int
    {
        $count = (int) $examination->examQuestions()->count();

        if ($count > 0) {
            return $count;
        }

        return max(1, (int) ($examination->total_items ?: 0));
    }

    protected function formatDuration(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds), 60);
        $remaining = max(0, $seconds) % 60;

        return sprintf('%d:%02d', $minutes, $remaining);
    }

    protected function relativeTime(?Carbon $time): string
    {
        if (! $time) {
            return '—';
        }

        if ($time->gt(now()->subSeconds(30))) {
            return 'Just now';
        }

        return $time->diffForHumans();
    }

    /**
     * @return array{expires_at: \Illuminate\Support\Carbon, duration_seconds: int}
     */
    protected function timerResetAttributes(ExaminationAttempt $attempt): array
    {
        $attempt->loadMissing('examination');
        $durationMinutes = max(1, (int) ($attempt->examination->duration_minutes ?: config('examination.default_duration_minutes', 60)));
        $durationSeconds = max(1, (int) ($attempt->duration_seconds ?: ($durationMinutes * 60)));

        return [
            'expires_at' => now()->addSeconds($durationSeconds),
            'duration_seconds' => $durationSeconds,
        ];
    }

    protected function assertCanMonitor(User $user, ?Examination $examination): void
    {
        if (! $examination) {
            throw new InvalidArgumentException('Examination record not found for this attempt.');
        }

        if (! $this->access->canMonitor($user, $examination)) {
            throw new InvalidArgumentException('You are not authorized to monitor this examination.');
        }
    }
}
