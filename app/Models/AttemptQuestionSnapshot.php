<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AttemptQuestionSnapshot extends Model
{
    protected $fillable = [
        'examination_attempt_id', 'question_id', 'display_order',
        'question_snapshot', 'choice_order', 'points',
    ];

    protected function casts(): array
    {
        return [
            'question_snapshot' => 'array',
            'choice_order' => 'array',
            'points' => 'decimal:2',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExaminationAttempt::class, 'examination_attempt_id');
    }
}
