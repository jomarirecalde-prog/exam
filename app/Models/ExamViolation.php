<?php

namespace App\Models;

use App\Enums\ViolationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamViolation extends Model
{
    protected $fillable = [
        'examination_attempt_id',
        'student_id',
        'examination_id',
        'violation_type',
        'warning_number',
        'detected_at',
        'metadata',
        'client_event_id',
    ];

    protected function casts(): array
    {
        return [
            'violation_type' => ViolationType::class,
            'detected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExaminationAttempt::class, 'examination_attempt_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }
}
