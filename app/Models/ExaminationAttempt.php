<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\SubmissionMode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class ExaminationAttempt extends Model
{
    protected $fillable = [
        'uuid', 'attempt_code', 'examination_id', 'examination_version_id', 'student_id',
        'attempt_number', 'started_at', 'submitted_at', 'expires_at', 'duration_seconds',
        'status', 'score', 'percentage', 'passed', 'submission_mode', 'sync_status',
        'device_info', 'ip_address', 'tab_switch_count', 'suspicious_activity_count', 'question_order',
        'warning_count', 'policy_accepted_at', 'policy_version', 'locked_at', 'lock_reason',
        'reactivated_at', 'reactivated_by', 'reactivation_reason', 'reactivation_count',
        'offline_enabled', 'offline_prepared_at', 'offline_session_id', 'authorized_device_id',
        'last_synced_at', 'pending_submission_at', 'offline_timing_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'submission_mode' => SubmissionMode::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'policy_accepted_at' => 'datetime',
            'locked_at' => 'datetime',
            'reactivated_at' => 'datetime',
            'offline_enabled' => 'boolean',
            'offline_prepared_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'pending_submission_at' => 'datetime',
            'score' => 'decimal:2',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'question_order' => 'array',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class)->withTrashed();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function grade(): HasOne
    {
        return $this->hasOne(Grade::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(AttemptQuestionSnapshot::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(ExamViolation::class);
    }

    public function policyAcceptances(): HasMany
    {
        return $this->hasMany(ExamPolicyAcceptance::class);
    }

    public function reactivationLogs(): HasMany
    {
        return $this->hasMany(ExamReactivationLog::class);
    }

    public function reactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }

    public function syncEvents(): HasMany
    {
        return $this->hasMany(ExamSyncEvent::class);
    }

    public function maxWarnings(): int
    {
        return (int) config('examination.max_violation_warnings', 3);
    }

    public function isLockedForViolations(): bool
    {
        $status = $this->status instanceof AttemptStatus
            ? $this->status
            : AttemptStatus::tryFrom((string) $this->getRawOriginal('status'));

        if ($status === AttemptStatus::LockedViolationLimit) {
            return true;
        }

        if ($this->locked_at !== null) {
            return true;
        }

        return $status === AttemptStatus::InProgress
            && (int) $this->warning_count >= $this->maxWarnings();
    }

    public function canBeReactivated(): bool
    {
        if ($this->status instanceof AttemptStatus && $this->status->isTerminal()) {
            return false;
        }

        return $this->isLockedForViolations();
    }

    public function remainingSeconds(): int
    {
        if (! $this->expires_at) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
