<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StudentAnswer extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $answer) {
            if (empty($answer->uuid)) {
                $answer->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'uuid', 'examination_attempt_id', 'question_id', 'answer', 'is_flagged',
        'is_correct', 'points_earned', 'requires_manual_grading', 'is_graded', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'is_flagged' => 'boolean',
            'is_correct' => 'boolean',
            'points_earned' => 'decimal:2',
            'requires_manual_grading' => 'boolean',
            'is_graded' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExaminationAttempt::class, 'examination_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function essayAnswer(): HasOne
    {
        return $this->hasOne(EssayAnswer::class);
    }
}
