<?php

namespace App\Services\Questions;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;

class QuestionCsvExporter
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters = []): string
    {
        $questions = $this->query($filters)->get();
        $rows = [$this->headers()];

        foreach ($questions as $question) {
            $rows[] = $this->mapQuestion($question);
        }

        return $this->rowsToCsv($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filename(array $filters = []): string
    {
        $parts = ['questions', now()->format('Y-m-d')];

        if (! empty($filters['subject_id'])) {
            $parts[] = 'subject-'.$filters['subject_id'];
        }

        if (! empty($filters['difficulty'])) {
            $parts[] = strtolower((string) $filters['difficulty']);
        }

        return implode('_', $parts).'.csv';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters = []): int
    {
        return $this->query($filters)->count();
    }

    /**
     * @return list<string>
     */
    protected function headers(): array
    {
        return [
            'question',
            'type',
            'choice_a',
            'choice_b',
            'choice_c',
            'choice_d',
            'correct_answer',
            'points',
            'difficulty',
            'topic',
            'sample_answer',
            'subject_code',
        ];
    }

    /**
     * @return list<string>
     */
    protected function mapQuestion(Question $question): array
    {
        $choices = $question->choices->keyBy(fn ($choice) => strtoupper((string) $choice->label));
        $topic = is_array($question->metadata) ? ($question->metadata['topic'] ?? '') : '';
        $correctAnswer = $this->formatCorrectAnswer($question);

        return [
            $this->escapeCell($question->question_text),
            $question->type->value,
            $this->escapeCell($choices->get('A')?->choice_text ?? ''),
            $this->escapeCell($choices->get('B')?->choice_text ?? ''),
            $this->escapeCell($choices->get('C')?->choice_text ?? ''),
            $this->escapeCell($choices->get('D')?->choice_text ?? ''),
            $this->escapeCell($correctAnswer),
            (string) $question->points,
            ucfirst(strtolower((string) $question->difficulty)),
            $this->escapeCell($topic),
            $this->escapeCell((string) ($question->explanation ?? '')),
            $this->escapeCell($question->subject?->code ?? ''),
        ];
    }

    protected function formatCorrectAnswer(Question $question): string
    {
        $answer = $question->correct_answer;

        if ($question->type->value === 'multiple_choice') {
            return strtoupper((string) (is_array($answer) ? ($answer[0] ?? '') : $answer));
        }

        if ($question->type->value === 'true_false') {
            return is_array($answer) ? (string) ($answer[0] ?? '') : (string) $answer;
        }

        if ($question->type->value === 'identification') {
            return is_array($answer) ? (string) ($answer[0] ?? '') : (string) $answer;
        }

        return '';
    }

    protected function escapeCell(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        if (preg_match('/^[=+\-@]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function query(array $filters): Builder
    {
        $query = Question::query()
            ->with(['choices', 'subject'])
            ->where('is_archived', false)
            ->latest();

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', (int) $filters['subject_id']);
        }

        if (! empty($filters['difficulty'])) {
            $query->where('difficulty', strtolower((string) $filters['difficulty']));
        }

        if (! empty($filters['type'])) {
            $query->where('type', (string) $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where('question_text', 'like', '%'.$search.'%');
        }

        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', array_map('intval', $filters['ids']));
        }

        return $query;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function rowsToCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return "\xEF\xBB\xBF".$csv;
    }
}
