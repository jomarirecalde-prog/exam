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

    /**
     * @return array<string, mixed>
     */
    public function toExamPayload(?float $pointsOverride = null): array
    {
        $type = $this->type->value ?? (string) $this->type;

        $payload = [
            'id' => $this->id,
            'text' => $this->question_text,
            'type' => $type,
            'points' => $pointsOverride ?? $this->points,
            'instructions' => is_array($this->metadata) ? ($this->metadata['instructions'] ?? null) : null,
            'image_url' => $this->image_path ? asset('storage/'.$this->image_path) : null,
            'choices' => [],
        ];

        if (! in_array($type, [QuestionType::MultipleChoice->value, QuestionType::TrueFalse->value], true)) {
            return $payload;
        }

        $payload['choices'] = $this->choices->map(function (QuestionChoice $choice) use ($type) {
            $id = $type === QuestionType::TrueFalse->value
                ? strtolower((string) $choice->label)
                : strtoupper((string) $choice->label);

            return [
                'id' => $id,
                'text' => $choice->choice_text,
            ];
        })->values()->all();

        return $payload;
    }
}
