<?php

namespace App\Services\Examinations;

use App\Enums\QuestionType;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\Grade;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ExamResultBreakdownService
{
    /**
     * @return array{
     *     show_correct_answers: bool,
     *     show_explanations: bool,
     *     items: list<array<string, mixed>>,
     *     summary: array{correct: int, incorrect: int, pending: int, unanswered: int, total: int}
     * }
     */
    public function build(Examination $examination, Grade $grade): array
    {
        $examination->loadMissing('settings');

        $attempt = $grade->attempt()
            ->with([
                'answers.question.choices',
                'answers.essayAnswer',
                'snapshots',
            ])
            ->first();

        if (! $attempt) {
            return $this->emptyBreakdown($examination);
        }

        $showCorrectAnswers = (bool) ($examination->settings?->show_correct_answers ?? false);
        $showExplanations = (bool) ($examination->settings?->show_explanations ?? false);

        $items = [];
        $summary = [
            'correct' => 0,
            'incorrect' => 0,
            'pending' => 0,
            'unanswered' => 0,
            'total' => 0,
        ];

        foreach ($this->resolveQuestions($attempt) as $index => $questionData) {
            $item = $this->buildItem(
                $index + 1,
                $questionData,
                $attempt->answers,
                $showCorrectAnswers,
                $showExplanations,
            );

            $items[] = $item;
            $summary['total']++;
            $summary[$item['outcome']] = ($summary[$item['outcome']] ?? 0) + 1;
        }

        return [
            'show_correct_answers' => $showCorrectAnswers,
            'show_explanations' => $showExplanations,
            'items' => $items,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{
     *     show_correct_answers: bool,
     *     show_explanations: bool,
     *     items: list<array<string, mixed>>,
     *     summary: array{correct: int, incorrect: int, pending: int, unanswered: int, total: int}
     * }
     */
    protected function emptyBreakdown(Examination $examination): array
    {
        return [
            'show_correct_answers' => (bool) ($examination->settings?->show_correct_answers ?? false),
            'show_explanations' => (bool) ($examination->settings?->show_explanations ?? false),
            'items' => [],
            'summary' => [
                'correct' => 0,
                'incorrect' => 0,
                'pending' => 0,
                'unanswered' => 0,
                'total' => 0,
            ],
        ];
    }

    /**
     * @return list<array{question: Question, points: float, snapshot: ?array<string, mixed>, display_order: int}>
     */
    protected function resolveQuestions(ExaminationAttempt $attempt): array
    {
        if ($attempt->snapshots->isNotEmpty()) {
            return $attempt->snapshots
                ->sortBy('display_order')
                ->map(function ($snapshot) {
                    $question = Question::with('choices')->find($snapshot->question_id);
                    $snapshotData = is_array($snapshot->question_snapshot) ? $snapshot->question_snapshot : [];

                    if (! $question) {
                        $question = new Question([
                            'id' => $snapshot->question_id,
                            'type' => $snapshotData['type'] ?? QuestionType::MultipleChoice->value,
                            'question_text' => $snapshotData['text'] ?? 'Question',
                        ]);
                        $question->id = $snapshot->question_id;
                    }

                    return [
                        'question' => $question,
                        'points' => (float) $snapshot->points,
                        'snapshot' => $snapshotData,
                        'display_order' => (int) $snapshot->display_order,
                    ];
                })
                ->values()
                ->all();
        }

        return $attempt->answers
            ->map(function (StudentAnswer $answer) {
                if (! $answer->question) {
                    return null;
                }

                return [
                    'question' => $answer->question,
                    'points' => (float) ($answer->question->points ?? 1),
                    'snapshot' => null,
                    'display_order' => 0,
                ];
            })
            ->filter()
            ->values()
            ->map(function (array $item, int $index) {
                $item['display_order'] = $index + 1;

                return $item;
            })
            ->all();
    }

    /**
     * @param  Collection<int, StudentAnswer>  $answers
     * @param  array{question: Question, points: float, snapshot: ?array<string, mixed>, display_order: int}  $questionData
     * @return array<string, mixed>
     */
    protected function buildItem(
        int $number,
        array $questionData,
        Collection $answers,
        bool $showCorrectAnswers,
        bool $showExplanations,
    ): array {
        $question = $questionData['question'];
        $points = $questionData['points'];
        $snapshot = $questionData['snapshot'];
        $type = $question->type instanceof QuestionType
            ? $question->type
            : QuestionType::tryFrom((string) $question->type);

        /** @var StudentAnswer|null $answer */
        $answer = $answers->firstWhere('question_id', $question->id);
        $choices = $this->resolveChoices($question, $snapshot);
        $studentValue = $this->extractAnswerValue($answer);
        $hasResponse = $this->hasResponse($studentValue, $answer);

        if (! $hasResponse) {
            $outcome = 'unanswered';
        } elseif ($answer?->requires_manual_grading) {
            $outcome = $answer->is_graded ? 'graded' : 'pending';
        } elseif ($answer?->is_correct) {
            $outcome = 'correct';
        } else {
            $outcome = 'incorrect';
        }

        $item = [
            'number' => $number,
            'question_id' => $question->id,
            'type' => $type?->value ?? (string) $question->type,
            'type_label' => Str::headline(str_replace('_', ' ', $type?->value ?? (string) $question->type)),
            'text' => $snapshot['text'] ?? $question->question_text,
            'points' => $points,
            'points_earned' => $answer?->points_earned !== null ? (float) $answer->points_earned : null,
            'outcome' => $outcome,
            'student_answer' => $this->formatStudentAnswer($type, $studentValue, $choices, $answer),
            'requires_manual_grading' => (bool) $answer?->requires_manual_grading,
            'is_graded' => (bool) $answer?->is_graded,
            'feedback' => $answer?->essayAnswer?->feedback,
        ];

        if ($showCorrectAnswers) {
            $item['correct_answer'] = $this->formatCorrectAnswer($type, $question->correct_answer, $choices);
        }

        if ($showExplanations && filled($question->explanation)) {
            $item['explanation'] = $question->explanation;
        }

        return $item;
    }

    /**
     * @param  ?array<string, mixed>  $snapshot
     * @return list<array{id: string, text: string}>
     */
    protected function resolveChoices(Question $question, ?array $snapshot): array
    {
        if (is_array($snapshot['choices'] ?? null) && $snapshot['choices'] !== []) {
            return array_values($snapshot['choices']);
        }

        return $question->choices
            ->map(function ($choice) use ($question) {
                $type = $question->type instanceof QuestionType
                    ? $question->type
                    : QuestionType::tryFrom((string) $question->type);

                $id = $type === QuestionType::TrueFalse
                    ? strtolower((string) $choice->label)
                    : strtoupper((string) $choice->label);

                return [
                    'id' => $id,
                    'text' => $choice->choice_text,
                ];
            })
            ->values()
            ->all();
    }

    protected function extractAnswerValue(?StudentAnswer $answer): mixed
    {
        if (! $answer || $answer->answer === null) {
            return null;
        }

        $stored = $answer->answer;

        if (is_array($stored) && array_key_exists('value', $stored)) {
            return $stored['value'];
        }

        return $stored;
    }

    protected function hasResponse(mixed $studentValue, ?StudentAnswer $answer): bool
    {
        if ($answer?->essayAnswer?->answer_text) {
            return true;
        }

        if ($studentValue === null || $studentValue === '' || $studentValue === []) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<array{id: string, text: string}>  $choices
     */
    protected function formatStudentAnswer(
        ?QuestionType $type,
        mixed $value,
        array $choices,
        ?StudentAnswer $answer,
    ): ?string {
        if ($answer?->essayAnswer?->answer_text) {
            return trim((string) $answer->essayAnswer->answer_text);
        }

        return $this->formatAnswerValue($type, $value, $choices);
    }

    /**
     * @param  list<array{id: string, text: string}>  $choices
     */
    protected function formatCorrectAnswer(?QuestionType $type, mixed $value, array $choices): ?string
    {
        return $this->formatAnswerValue($type, $value, $choices);
    }

    /**
     * @param  list<array{id: string, text: string}>  $choices
     */
    protected function formatAnswerValue(?QuestionType $type, mixed $value, array $choices): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($type) {
            QuestionType::MultipleChoice, QuestionType::TrueFalse => $this->formatChoiceValue($value, $choices),
            QuestionType::MultipleSelect => $this->formatChoiceList($value, $choices),
            QuestionType::Matching => $this->formatMatchingValue(is_array($value) ? $value : []),
            QuestionType::Enumeration => $this->formatListValue(is_array($value) ? $value : [$value]),
            QuestionType::Essay, QuestionType::ShortAnswer, QuestionType::Identification, QuestionType::FillBlank => trim((string) (is_array($value) ? ($value['value'] ?? reset($value)) : $value)),
            default => is_scalar($value) ? trim((string) $value) : json_encode($value),
        };
    }

    /**
     * @param  list<array{id: string, text: string}>  $choices
     */
    protected function formatChoiceValue(mixed $value, array $choices): string
    {
        $id = is_array($value) ? (string) ($value[0] ?? reset($value)) : (string) $value;

        foreach ($choices as $choice) {
            if (strcasecmp((string) $choice['id'], $id) === 0) {
                return (string) $choice['text'];
            }
        }

        return $id;
    }

    /**
     * @param  list<array{id: string, text: string}>  $choices
     */
    protected function formatChoiceList(mixed $value, array $choices): string
    {
        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->map(fn ($item) => $this->formatChoiceValue($item, $choices))
            ->filter()
            ->implode(', ');
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    protected function formatMatchingValue(array $value): string
    {
        if ($value === []) {
            return '';
        }

        return collect($value)
            ->map(fn ($matched, $prompt) => trim((string) $prompt).': '.trim((string) $matched))
            ->implode('; ');
    }

    /**
     * @param  list<mixed>  $value
     */
    protected function formatListValue(array $value): string
    {
        return collect($value)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->implode(', ');
    }
}
