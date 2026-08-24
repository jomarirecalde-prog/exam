<?php

namespace App\Services\Questions;

use App\Models\Examination;
use App\Models\ExaminationQuestion;
use App\Models\Question;
use App\Models\QuestionChoice;
use Illuminate\Support\Str;

class ExaminationQuestionService
{
    /**
     * @param  list<array<string, mixed>>  $questions
     */
    public function sync(Examination $examination, array $questions, ?int $instructorId): void
    {
        $existing = $examination->examQuestions()->with('question.choices')->get();
        $existingByOrder = $existing->keyBy('order');
        $keptQuestionIds = [];

        foreach ($questions as $index => $payload) {
            $order = $index + 1;
            $examQuestion = $existingByOrder->get($order);
            $question = $examQuestion?->question;

            if ($question) {
                $this->updateQuestion($question, $payload, $examination->subject_id, $instructorId);
            } else {
                $question = $this->createQuestion($payload, $examination->subject_id, $instructorId);
                ExaminationQuestion::create([
                    'examination_id' => $examination->id,
                    'question_id' => $question->id,
                    'order' => $order,
                ]);
            }

            $keptQuestionIds[] = $question->id;
        }

        $examination->examQuestions()
            ->whereNotIn('question_id', $keptQuestionIds)
            ->each(function (ExaminationQuestion $examQuestion) {
                $examQuestion->question?->choices()->delete();
                $examQuestion->question?->delete();
                $examQuestion->delete();
            });

        $examination->update(['total_items' => count($questions)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toWizardPayload(Examination $examination): array
    {
        return $examination->examQuestions()
            ->with(['question.choices'])
            ->orderBy('order')
            ->get()
            ->map(function (ExaminationQuestion $examQuestion, int $index) {
                $question = $examQuestion->question;

                if (! $question) {
                    return null;
                }

                return $this->mapToWizard($question, $index + 1);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function createQuestion(array $payload, ?int $subjectId, ?int $instructorId): Question
    {
        $question = Question::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $subjectId,
            'instructor_id' => $instructorId,
            'type' => $payload['type'],
            'question_text' => $payload['text'],
            'correct_answer' => $this->dbCorrectAnswer($payload),
            'points' => $payload['points'] ?? 1,
            'explanation' => $payload['sampleAnswer'] ?? null,
            'difficulty' => strtolower((string) ($payload['difficulty'] ?? 'medium')),
            'metadata' => filled($payload['topic'] ?? null) ? ['topic' => $payload['topic']] : null,
        ]);

        $this->syncChoices($question, $payload);

        return $question;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function updateQuestion(Question $question, array $payload, ?int $subjectId, ?int $instructorId): void
    {
        $question->update([
            'subject_id' => $subjectId,
            'instructor_id' => $instructorId,
            'type' => $payload['type'],
            'question_text' => $payload['text'],
            'correct_answer' => $this->dbCorrectAnswer($payload),
            'points' => $payload['points'] ?? 1,
            'explanation' => $payload['sampleAnswer'] ?? null,
            'difficulty' => strtolower((string) ($payload['difficulty'] ?? 'medium')),
            'metadata' => filled($payload['topic'] ?? null) ? ['topic' => $payload['topic']] : null,
        ]);

        $question->choices()->delete();
        $this->syncChoices($question, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function syncChoices(Question $question, array $payload): void
    {
        if (($payload['type'] ?? '') === 'multiple_choice') {
            foreach ($payload['choices'] ?? [] as $index => $choice) {
                if (($choice['text'] ?? '') === '') {
                    continue;
                }

                QuestionChoice::create([
                    'question_id' => $question->id,
                    'label' => $choice['id'],
                    'choice_text' => $choice['text'],
                    'is_correct' => strtoupper((string) ($payload['correctAnswer'] ?? '')) === strtoupper((string) $choice['id']),
                    'order' => $index,
                ]);
            }

            return;
        }

        if (($payload['type'] ?? '') === 'true_false') {
            foreach ($payload['choices'] ?? [] as $index => $choice) {
                QuestionChoice::create([
                    'question_id' => $question->id,
                    'label' => $choice['id'],
                    'choice_text' => $choice['text'],
                    'is_correct' => (string) ($payload['correctAnswer'] ?? '') === (string) $choice['id'],
                    'order' => $index,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function dbCorrectAnswer(array $payload): mixed
    {
        return match ($payload['type'] ?? '') {
            'multiple_choice' => [strtolower((string) ($payload['correctAnswer'] ?? ''))],
            'true_false' => [(string) ($payload['correctAnswer'] ?? '')],
            'identification' => [(string) ($payload['correctAnswer'] ?? '')],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapToWizard(Question $question, int $id): array
    {
        $topic = is_array($question->metadata) ? ($question->metadata['topic'] ?? '') : '';
        $correctAnswer = $question->correct_answer;
        $type = $question->type->value;

        $payload = [
            'id' => $id,
            'type' => $type,
            'text' => $question->question_text,
            'points' => (int) $question->points,
            'difficulty' => ucfirst(strtolower((string) $question->difficulty)),
            'topic' => $topic,
            'sampleAnswer' => (string) ($question->explanation ?? ''),
            'choices' => [],
            'correctAnswer' => '',
        ];

        if ($type === 'multiple_choice') {
            $payload['choices'] = $question->choices->map(fn (QuestionChoice $choice) => [
                'id' => strtoupper((string) $choice->label),
                'text' => $choice->choice_text,
            ])->all();
            $payload['correctAnswer'] = strtoupper((string) (is_array($correctAnswer) ? ($correctAnswer[0] ?? '') : $correctAnswer));
        } elseif ($type === 'true_false') {
            $payload['choices'] = [
                ['id' => 'true', 'text' => 'True'],
                ['id' => 'false', 'text' => 'False'],
            ];
            $payload['correctAnswer'] = is_array($correctAnswer) ? (string) ($correctAnswer[0] ?? 'true') : (string) $correctAnswer;
        } elseif ($type === 'identification') {
            $payload['correctAnswer'] = is_array($correctAnswer) ? (string) ($correctAnswer[0] ?? '') : (string) $correctAnswer;
        }

        return $payload;
    }
}
