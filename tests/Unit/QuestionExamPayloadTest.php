<?php

namespace Tests\Unit;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuestionExamPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_essay_payload_excludes_multiple_choice_options(): void
    {
        $question = Question::create([
            'uuid' => (string) Str::uuid(),
            'type' => QuestionType::Essay,
            'question_text' => 'Explain normalization.',
            'points' => 5,
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label' => 'A',
            'choice_text' => 'Legacy option',
            'is_correct' => false,
            'order' => 0,
        ]);

        $payload = $question->fresh('choices')->toExamPayload();

        $this->assertSame('essay', $payload['type']);
        $this->assertSame([], $payload['choices']);
    }

    public function test_multiple_choice_payload_includes_options(): void
    {
        $question = Question::create([
            'uuid' => (string) Str::uuid(),
            'type' => QuestionType::MultipleChoice,
            'question_text' => 'Pick one.',
            'points' => 1,
        ]);

        QuestionChoice::create([
            'question_id' => $question->id,
            'label' => 'A',
            'choice_text' => 'First',
            'is_correct' => true,
            'order' => 0,
        ]);

        $payload = $question->fresh('choices')->toExamPayload();

        $this->assertSame('multiple_choice', $payload['type']);
        $this->assertCount(1, $payload['choices']);
        $this->assertSame('A', $payload['choices'][0]['id']);
    }

    public function test_true_false_payload_uses_lowercase_choice_ids(): void
    {
        $question = Question::create([
            'uuid' => (string) Str::uuid(),
            'type' => QuestionType::TrueFalse,
            'question_text' => 'Statement.',
            'points' => 1,
        ]);

        foreach (['true' => 'True', 'false' => 'False'] as $id => $text) {
            QuestionChoice::create([
                'question_id' => $question->id,
                'label' => $id,
                'choice_text' => $text,
                'is_correct' => $id === 'true',
                'order' => $id === 'true' ? 0 : 1,
            ]);
        }

        $payload = $question->fresh('choices')->toExamPayload();

        $this->assertSame('true_false', $payload['type']);
        $this->assertSame(['true', 'false'], array_column($payload['choices'], 'id'));
    }
}
