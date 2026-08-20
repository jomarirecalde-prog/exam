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
    ];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'submission_mode' => SubmissionMode::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
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
}
