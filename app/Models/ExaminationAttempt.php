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
            'score' => 'decimal:2',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'question_order' => 'array',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
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

    public function maxWarnings(): int
    {
        return (int) config('examination.max_violation_warnings', 3);
    }

    public function remainingSeconds(): int
    {
        if (! $this->expires_at) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
