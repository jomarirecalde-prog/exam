<?php

namespace App\Services\Grading;

use App\Enums\QuestionType;
use App\Enums\ResultStatus;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\Grade;
use App\Models\GradingFormula;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GradingResult
{
    public function __construct(
        public readonly float $totalPoints,
        public readonly float $earnedPoints,
        public readonly float $rawScore,
        public readonly float $percentage,
        public readonly ?string $letterGrade,
        public readonly bool $passed,
        public readonly ResultStatus $status,
        public readonly bool $requiresManualGrading,
    ) {}
}

class GradingEngine
{
    public function gradeAttempt(ExaminationAttempt $attempt): GradingResult
    {
        $attempt->load(['answers.question.choices', 'examination', 'snapshots']);

        $examination = $attempt->examination;
        $answers = $attempt->answers;
        $requiresManualGrading = false;
        $earnedPoints = 0.0;
        $totalPoints = 0.0;

        foreach ($this->resolveQuestions($attempt) as $questionData) {
            $question = $questionData['question'];
            $points = (float) $questionData['points'];
            $totalPoints += $points;

            /** @var StudentAnswer|null $answer */
            $answer = $answers->firstWhere('question_id', $question->id);

            if ($question->type->requiresManualGrading()) {
                $requiresManualGrading = true;
                $earned = $answer?->is_graded ? (float) ($answer->points_earned ?? 0) : 0.0;

                if ($answer) {
                    $this->persistAnswerGrade($answer, null, $earned, true, $answer->is_graded);
                }

                $earnedPoints += $earned;

                continue;
            }

            $isCorrect = $answer !== null && $this->evaluateObjectiveAnswer($question, $answer->answer ?? null);
            $earned = $isCorrect ? $points : 0.0;
            $earnedPoints += $earned;

            if ($answer) {
                $this->persistAnswerGrade($answer, $isCorrect, $earned, false, true);
            }
        }

        $percentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0.0;
        $passed = $this->determinePassing($examination, $earnedPoints, $percentage);
        $letterGrade = $this->resolveLetterGrade($percentage);
        $status = $requiresManualGrading && ! $this->allSubjectiveGraded($answers)
            ? ResultStatus::PendingGrading
            : ($passed ? ResultStatus::Passed : ResultStatus::Failed);

        return new GradingResult(
            totalPoints: round($totalPoints, 2),
            earnedPoints: round($earnedPoints, 2),
            rawScore: round($earnedPoints, 2),
            percentage: $percentage,
            letterGrade: $letterGrade,
            passed: $passed,
            status: $status,
            requiresManualGrading: $requiresManualGrading,
        );
    }

    public function applyToAttempt(ExaminationAttempt $attempt): Grade
    {
        $result = $this->gradeAttempt($attempt);

        $attempt->update([
            'score' => $result->rawScore,
            'percentage' => $result->percentage,
            'passed' => $result->passed,
        ]);

        return Grade::updateOrCreate(
            ['examination_attempt_id' => $attempt->id],
            [
                'examination_id' => $attempt->examination_id,
                'student_id' => $attempt->student_id,
                'raw_score' => $result->rawScore,
                'total_points' => $result->totalPoints,
                'percentage' => $result->percentage,
                'letter_grade' => $result->letterGrade,
                'status' => $result->status,
                'passed' => $result->passed,
                'grading_formula_id' => GradingFormula::where('is_default', true)->value('id'),
            ]
        );
    }

    public function evaluateObjectiveAnswer(Question $question, mixed $studentAnswer): bool
    {
        if ($studentAnswer === null || $studentAnswer === '' || $studentAnswer === []) {
            return false;
        }

        $correct = $question->correct_answer;

        return match ($question->type) {
            QuestionType::MultipleChoice, QuestionType::TrueFalse => $this->compareScalar(
                $this->normalizeScalar($studentAnswer),
                $this->normalizeScalar($correct)
            ),
            QuestionType::MultipleSelect => $this->compareSets(
                $this->normalizeArray($studentAnswer),
                $this->normalizeArray($correct)
            ),
            QuestionType::Identification, QuestionType::ShortAnswer, QuestionType::FillBlank => $this->compareTextAnswers(
                $studentAnswer,
                $correct
            ),
            QuestionType::Enumeration => $this->compareEnumeration(
                $this->normalizeArray($studentAnswer),
                $this->normalizeArray($correct)
            ),
            QuestionType::Matching => $this->compareMatching(
                is_array($studentAnswer) ? $studentAnswer : [],
                is_array($correct) ? $correct : []
            ),
            default => false,
        };
    }

    protected function resolveQuestions(ExaminationAttempt $attempt): Collection
    {
        if ($attempt->snapshots->isNotEmpty()) {
            return $attempt->snapshots->sortBy('display_order')->map(function ($snapshot) {
                $data = $snapshot->question_snapshot;
                $question = new Question([
                    'id' => $snapshot->question_id,
                    'type' => $data['type'],
                    'correct_answer' => $data['correct_answer'] ?? null,
                    'points' => $snapshot->points,
                ]);
                $question->id = $snapshot->question_id;
                $question->setRawAttributes(array_merge($question->getAttributes(), [
                    'type' => $data['type'],
                    'correct_answer' => json_encode($data['correct_answer'] ?? null),
                ]));

                return [
                    'question' => Question::find($snapshot->question_id) ?? $question,
                    'points' => (float) $snapshot->points,
                ];
            });
        }

        return $attempt->answers->map(function (StudentAnswer $answer) {
            return [
                'question' => $answer->question,
                'points' => (float) ($answer->question->points ?? 1),
            ];
        })->filter(fn ($item) => $item['question'] !== null);
    }

    protected function persistAnswerGrade(
        StudentAnswer $answer,
        ?bool $isCorrect,
        float $pointsEarned,
        bool $requiresManualGrading,
        bool $isGraded
    ): void {
        $answer->update([
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'requires_manual_grading' => $requiresManualGrading,
            'is_graded' => $isGraded,
        ]);
    }

    protected function determinePassing(Examination $examination, float $earnedPoints, float $percentage): bool
    {
        if ($examination->passing_score !== null) {
            return $earnedPoints >= (float) $examination->passing_score;
        }

        return $percentage >= (float) ($examination->passing_percentage ?? config('examination.default_passing_percentage', 75));
    }

    protected function resolveLetterGrade(float $percentage): ?string
    {
        return match (true) {
            $percentage >= 97 => 'A+',
            $percentage >= 93 => 'A',
            $percentage >= 90 => 'A-',
            $percentage >= 87 => 'B+',
            $percentage >= 83 => 'B',
            $percentage >= 80 => 'B-',
            $percentage >= 77 => 'C+',
            $percentage >= 75 => 'C',
            default => 'F',
        };
    }

    protected function allSubjectiveGraded(Collection $answers): bool
    {
        return $answers
            ->filter(fn (StudentAnswer $answer) => $answer->requires_manual_grading)
            ->every(fn (StudentAnswer $answer) => $answer->is_graded);
    }

    protected function normalizeScalar(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return Str::lower(trim((string) $value));
    }

    protected function normalizeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [$this->normalizeScalar($value)];
        }

        $normalized = array_map(fn ($item) => $this->normalizeScalar($item), $value);
        sort($normalized);

        return array_values($normalized);
    }

    protected function compareScalar(string $student, string $correct): bool
    {
        return $student !== '' && $student === $correct;
    }

    protected function compareSets(array $student, array $correct): bool
    {
        return ! empty($correct) && $student === $correct;
    }

    protected function compareTextAnswers(mixed $studentAnswer, mixed $correctAnswer): bool
    {
        $accepted = is_array($correctAnswer) ? $correctAnswer : [$correctAnswer];
        $student = $this->normalizeScalar($studentAnswer);

        foreach ($accepted as $option) {
            if ($student === $this->normalizeScalar($option)) {
                return true;
            }
        }

        return false;
    }

    protected function compareEnumeration(array $student, array $correct): bool
    {
        if (empty($correct)) {
            return false;
        }

        $normalizedCorrect = array_map(fn ($item) => $this->normalizeScalar($item), $correct);

        foreach ($student as $index => $item) {
            if (! isset($normalizedCorrect[$index])) {
                return false;
            }

            if ($this->normalizeScalar($item) !== $normalizedCorrect[$index]) {
                return false;
            }
        }

        return count($student) === count($normalizedCorrect);
    }

    protected function compareMatching(array $student, array $correct): bool
    {
        if (empty($correct)) {
            return false;
        }

        foreach ($correct as $key => $value) {
            if (! array_key_exists($key, $student)) {
                return false;
            }

            if ($this->normalizeScalar($student[$key]) !== $this->normalizeScalar($value)) {
                return false;
            }
        }

        return true;
    }
}
