<?php

namespace App\Services\Grading;

use App\Models\EssayAnswer;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\Grade;
use App\Models\StudentAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ManualGradingService
{
    public function __construct(protected GradingEngine $gradingEngine) {}

    public function gradeAnswer(
        Examination $examination,
        StudentAnswer $answer,
        float $pointsEarned,
        ?string $feedback,
        User $grader,
    ): Grade {
        $answer->loadMissing(['attempt', 'question', 'essayAnswer']);

        $attempt = $answer->attempt;

        if (! $attempt || (int) $attempt->examination_id !== (int) $examination->id) {
            throw new InvalidArgumentException('Answer does not belong to this examination.');
        }

        if (! $answer->requires_manual_grading) {
            throw new InvalidArgumentException('This answer does not require manual grading.');
        }

        $maxPoints = $this->maxPointsForAnswer($answer, $attempt);

        if ($pointsEarned < 0 || $pointsEarned > $maxPoints) {
            throw new InvalidArgumentException("Points must be between 0 and {$maxPoints}.");
        }

        DB::transaction(function () use ($answer, $pointsEarned, $feedback, $grader) {
            $answer->update([
                'points_earned' => $pointsEarned,
                'is_graded' => true,
                'is_correct' => null,
            ]);

            EssayAnswer::updateOrCreate(
                ['student_answer_id' => $answer->id],
                [
                    'answer_text' => $answer->essayAnswer?->answer_text ?? $this->extractAnswerText($answer),
                    'score' => $pointsEarned,
                    'feedback' => $feedback,
                    'graded_by' => $grader->id,
                    'graded_at' => now(),
                ],
            );
        });

        return $this->gradingEngine->applyToAttempt($attempt->fresh());
    }

    public function maxPointsForAnswer(StudentAnswer $answer, ExaminationAttempt $attempt): float
    {
        $attempt->loadMissing('snapshots');

        $snapshot = $attempt->snapshots->firstWhere('question_id', $answer->question_id);

        if ($snapshot) {
            return (float) $snapshot->points;
        }

        return (float) ($answer->question?->points ?? 1);
    }

    protected function extractAnswerText(StudentAnswer $answer): ?string
    {
        $stored = $answer->answer;

        if ($stored === null) {
            return null;
        }

        if (is_array($stored) && array_key_exists('value', $stored)) {
            $value = $stored['value'];
        } else {
            $value = $stored;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        return $text === '' ? null : $text;
    }
}
