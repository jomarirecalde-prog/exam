<?php

namespace App\Services\Examinations;

use App\Models\ExaminationAttempt;
use App\Models\StudentAnswer;

class ExamAttemptProgressService
{
    public function recordProgress(
        ExaminationAttempt $attempt,
        ?int $currentQuestionIndex = null,
        ?string $connectionStatus = null,
    ): ExaminationAttempt {
        $updates = ['last_activity_at' => now()];

        if ($currentQuestionIndex !== null && $currentQuestionIndex > 0) {
            $updates['current_question_index'] = $currentQuestionIndex;
        }

        if ($connectionStatus !== null && in_array($connectionStatus, ['online', 'offline', 'reconnecting'], true)) {
            $updates['connection_status'] = $connectionStatus;
        }

        $attempt->update($updates);

        return $attempt->fresh();
    }

    public function totalQuestions(ExaminationAttempt $attempt): int
    {
        $attempt->loadMissing('snapshots');

        if ($attempt->snapshots->isNotEmpty()) {
            return $attempt->snapshots->count();
        }

        $attempt->loadMissing('examination');

        return (int) $attempt->examination?->examQuestions()->count();
    }

    public function countAnsweredQuestions(ExaminationAttempt $attempt): int
    {
        $attempt->loadMissing(['answers', 'snapshots']);

        if ($attempt->answers->isEmpty()) {
            return 0;
        }

        $snapshotTypes = $attempt->snapshots->keyBy('question_id');

        return $attempt->answers->filter(function (StudentAnswer $answer) use ($snapshotTypes) {
            return $this->isAnswerFilled($answer, $snapshotTypes->get($answer->question_id)?->question_snapshot['type'] ?? null);
        })->count();
    }

    public function progressPercent(ExaminationAttempt $attempt): int
    {
        $total = $this->totalQuestions($attempt);

        if ($total < 1) {
            return 0;
        }

        return (int) round(($this->countAnsweredQuestions($attempt) / $total) * 100);
    }

    public function isAnswerFilled(StudentAnswer $answer, ?string $questionType = null): bool
    {
        $value = $answer->answer['value'] ?? $answer->answer;
        $type = $questionType ?? 'multiple_choice';

        if (in_array($type, ['essay', 'short_answer', 'identification'], true)) {
            return is_string($value) && trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
    }
}
