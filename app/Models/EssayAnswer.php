<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class EssayAnswer extends Model
{
    protected $fillable = [
        'student_answer_id', 'answer_text', 'score', 'feedback',
        'graded_by', 'graded_at', 'rubric_scores',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'graded_at' => 'datetime',
            'rubric_scores' => 'array',
        ];
    }

    public function studentAnswer(): BelongsTo
    {
        return $this->belongsTo(StudentAnswer::class);
    }
}
