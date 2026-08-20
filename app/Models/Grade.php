<?php

namespace App\Models;

use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $fillable = [
        'examination_attempt_id', 'examination_id', 'student_id', 'raw_score',
        'total_points', 'percentage', 'letter_grade', 'status', 'passed',
        'is_released', 'released_at', 'released_by', 'remarks', 'grading_formula_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ResultStatus::class,
            'raw_score' => 'decimal:2',
            'total_points' => 'decimal:2',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'is_released' => 'boolean',
            'released_at' => 'datetime',
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
