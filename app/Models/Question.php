<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'subject_id', 'instructor_id', 'question_category_id', 'topic_id',
        'learning_objective_id', 'type', 'question_text', 'image_path', 'attachment_path',
        'correct_answer', 'points', 'explanation', 'difficulty', 'metadata', 'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'correct_answer' => 'array',
            'metadata' => 'array',
            'points' => 'decimal:2',
            'is_archived' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function choices(): HasMany
    {
        return $this->hasMany(QuestionChoice::class)->orderBy('order');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }
}
