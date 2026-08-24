<?php

namespace App\Services\Questions;

use App\Models\Question;
use InvalidArgumentException;

class QuestionCsvImporter
{
    /** @var list<string> */
    protected array $supportedTypes = [
        'multiple_choice',
        'true_false',
        'identification',
        'essay',
    ];

    /**
     * @return array{
     *     questions: list<array<string, mixed>>,
     *     errors: list<string>,
     *     rowErrors: list<array{row: int, field: string, message: string}>,
     *     warnings: list<string>,
     *     imported: int,
     *     stats: array{total: int, valid: int, errors: int, duplicates: int},
     *     preview: list<array{row: int, question: string, type: string, topic: string, status: string, message: string|null}>
     * }
     */
    public function import(string $path, ?int $subjectId = null): array
    {
        $result = $this->parse($path, $subjectId);

        return [
            'questions' => $result['questions'],
            'errors' => array_map(
                fn (array $error) => $this->formatRowError($error),
                $result['rowErrors'],
            ),
            'rowErrors' => $result['rowErrors'],
            'warnings' => $result['warnings'],
            'imported' => count($result['questions']),
            'stats' => $result['stats'],
            'preview' => $result['preview'],
        ];
    }

    /**
     * @return array{
     *     questions: list<array<string, mixed>>,
     *     rowErrors: list<array{row: int, field: string, message: string}>,
     *     warnings: list<string>,
     *     stats: array{total: int, valid: int, errors: int, duplicates: int},
     *     preview: list<array{row: int, question: string, type: string, topic: string, status: string, message: string|null}>
     * }
     */
    public function parse(string $path, ?int $subjectId = null): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('Unable to read the uploaded CSV file.');
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return $this->emptyResult(['The CSV file is empty.']);
        }

        $columns = $this->normalizeHeader($header);
        $missing = array_diff(['question', 'type'], array_keys($columns));

        if ($missing !== []) {
            fclose($handle);

            return $this->emptyResult(['Missing required column(s): '.implode(', ', $missing).'.']);
        }

        $questions = [];
        $rowErrors = [];
        $warnings = [];
        $preview = [];
        $seenKeys = [];
        $existingKeys = $subjectId ? $this->existingQuestionKeys($subjectId) : [];
        $rowNumber = 1;
        $totalRows = 0;
        $duplicateCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isBlankRow($row)) {
                continue;
            }

            $totalRows++;
            $data = $this->rowToAssoc($columns, $row);

            try {
                $question = $this->mapRow($data, $rowNumber);
                $duplicateKey = $this->duplicateKey($question, $data);

                if (isset($seenKeys[$duplicateKey])) {
                    $duplicateCount++;
                    $rowErrors[] = [
                        'row' => $rowNumber,
                        'field' => 'question',
                        'message' => 'Duplicate question detected in this CSV file.',
                    ];
                    $preview[] = $this->previewRow($rowNumber, $data, 'duplicate', 'Duplicate question in CSV');

                    continue;
                }

                if ($subjectId && isset($existingKeys[$duplicateKey])) {
                    $duplicateCount++;
                    $rowErrors[] = [
                        'row' => $rowNumber,
                        'field' => 'question',
                        'message' => 'This question already exists in the question bank.',
                    ];
                    $preview[] = $this->previewRow($rowNumber, $data, 'duplicate', 'Question already exists');

                    continue;
                }

                $seenKeys[$duplicateKey] = $rowNumber;
                $questions[] = $question;
                $preview[] = $this->previewRow($rowNumber, $data, 'valid');
            } catch (InvalidArgumentException $exception) {
                [$field, $message] = $this->splitRowError($exception->getMessage(), $rowNumber);
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'field' => $field,
                    'message' => $message,
                ];
                $preview[] = $this->previewRow($rowNumber, $data, 'error', $message);
            }
        }

        fclose($handle);

        if ($totalRows === 0 && $rowErrors === []) {
            return $this->emptyResult(['No question rows were found in the CSV file.']);
        }

        return [
            'questions' => $questions,
            'rowErrors' => $rowErrors,
            'warnings' => $warnings,
            'stats' => [
                'total' => $totalRows,
                'valid' => count($questions),
                'errors' => count(array_filter($preview, fn (array $row) => $row['status'] === 'error')),
                'duplicates' => $duplicateCount,
            ],
            'preview' => array_slice($preview, 0, 10),
        ];
    }

    public function template(): string
    {
        $rows = [
            ['question', 'type', 'choice_a', 'choice_b', 'choice_c', 'choice_d', 'correct_answer', 'points', 'difficulty', 'topic', 'sample_answer'],
            ['Which of the following best describes an information system?', 'multiple_choice', 'Hardware only', 'People, processes, and technology', 'A programming language', 'A network protocol', 'B', '1', 'medium', 'IS Fundamentals', ''],
            ['An information system includes people, processes, and technology.', 'true_false', '', '', '', '', 'true', '1', 'easy', 'IS Fundamentals', ''],
            ['What acronym refers to the Central Processing Unit?', 'identification', '', '', '', '', 'CPU', '1', 'medium', 'Hardware', ''],
            ['Explain the role of information systems in modern organizations.', 'essay', '', '', '', '', '', '5', 'hard', 'IS Fundamentals', 'Sample grading notes or rubric.'],
        ];

        return $this->rowsToCsv($rows);
    }

    /**
     * @param  list<array{row: int, field: string, message: string}>  $rowErrors
     */
    public function errorReport(array $rowErrors): string
    {
        $rows = [['row', 'field', 'message']];

        foreach ($rowErrors as $error) {
            $rows[] = [
                (string) $error['row'],
                $error['field'],
                $error['message'],
            ];
        }

        return $this->rowsToCsv($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return array{created: int, updated: int, skipped: int}
     */
    public function persist(array $questions, int $subjectId, ?int $instructorId, string $mode = 'create'): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $existing = Question::query()
            ->where('subject_id', $subjectId)
            ->get(['id', 'question_text']);

        $existingMap = [];

        foreach ($existing as $question) {
            $existingMap[$this->normalizeText($question->question_text)] = $question->id;
        }

        foreach ($questions as $payload) {
            $key = $this->normalizeText($payload['text']);
            $existingId = $existingMap[$key] ?? null;

            if ($existingId && $mode === 'create') {
                $counts['skipped']++;

                continue;
            }

            if (! $existingId && $mode === 'update') {
                $counts['skipped']++;

                continue;
            }

            if ($existingId) {
                $this->updateQuestion($existingId, $payload, $subjectId, $instructorId);
                $counts['updated']++;
            } else {
                $question = $this->createQuestion($payload, $subjectId, $instructorId);
                $existingMap[$key] = $question->id;
                $counts['created']++;
            }
        }

        return $counts;
    }

    /**
     * @param  list<string>  $messages
     * @return array{
     *     questions: list<array<string, mixed>>,
     *     rowErrors: list<array{row: int, field: string, message: string}>,
     *     warnings: list<string>,
     *     stats: array{total: int, valid: int, errors: int, duplicates: int},
     *     preview: list<array{row: int, question: string, type: string, topic: string, status: string, message: string|null}>
     * }
     */
    protected function emptyResult(array $messages): array
    {
        $rowErrors = [];

        foreach ($messages as $message) {
            $rowErrors[] = [
                'row' => 0,
                'field' => 'file',
                'message' => $message,
            ];
        }

        return [
            'questions' => [],
            'rowErrors' => $rowErrors,
            'warnings' => [],
            'stats' => [
                'total' => 0,
                'valid' => 0,
                'errors' => count($messages),
                'duplicates' => 0,
            ],
            'preview' => [],
        ];
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

    /**
     * @return array<string, int>
     */
    protected function existingQuestionKeys(int $subjectId): array
    {
        $keys = [];

        Question::query()
            ->where('subject_id', $subjectId)
            ->pluck('question_text')
            ->each(function (string $text) use (&$keys) {
                $keys[$this->normalizeText($text)] = true;
            });

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, string>  $data
     */
    protected function duplicateKey(array $question, array $data): string
    {
        $subjectCode = strtolower(trim($data['subject_code'] ?? ''));

        return $subjectCode.'|'.$this->normalizeText($question['text']);
    }

    protected function normalizeText(string $text): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? $text));
    }

    /**
     * @param  array<string, string>  $data
     * @return array{row: int, question: string, type: string, topic: string, status: string, message: string|null}
     */
    protected function previewRow(int $rowNumber, array $data, string $status, ?string $message = null): array
    {
        return [
            'row' => $rowNumber,
            'question' => \Illuminate\Support\Str::limit($data['question'] ?? '', 80),
            'type' => $data['type'] ?? '',
            'topic' => $data['topic'] ?? '—',
            'status' => $status,
            'message' => $message,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitRowError(string $message, int $rowNumber): array
    {
        if (preg_match('/^Row \d+: (.+)$/', $message, $matches)) {
            $detail = $matches[1];

            if (str_contains($detail, ':')) {
                [$field, $rest] = explode(':', $detail, 2);

                return [trim($field), trim($rest)];
            }

            return ['question', $detail];
        }

        return ['file', $message];
    }

    /**
     * @param  array{row: int, field: string, message: string}  $error
     */
    protected function formatRowError(array $error): string
    {
        if ($error['row'] <= 0) {
            return $error['message'];
        }

        $field = ucfirst(str_replace('_', ' ', $error['field']));

        return "Row {$error['row']}\n{$field}: {$error['message']}";
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    protected function normalizeHeader(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $column) {
            $key = $this->normalizeKey((string) $column);

            if ($key !== '') {
                $columns[$key] = $index;
            }
        }

        return $columns;
    }

    protected function normalizeKey(string $column): string
    {
        $key = strtolower(trim($column));
        $key = str_replace([' ', '-'], '_', $key);

        return match ($key) {
            'question_text', 'prompt' => 'question',
            'question_type' => 'type',
            'answer', 'correct', 'correctanswer' => 'correct_answer',
            'choicea' => 'choice_a',
            'choiceb' => 'choice_b',
            'choicec' => 'choice_c',
            'choiced' => 'choice_d',
            'sample', 'rubric', 'sampleanswer', 'rubric_notes' => 'sample_answer',
            'subject', 'subjectcode' => 'subject_code',
            default => $key,
        };
    }

    /**
     * @param  array<string, int>  $columns
     * @param  list<string|null>  $row
     * @return array<string, string>
     */
    protected function rowToAssoc(array $columns, array $row): array
    {
        $data = [];

        foreach ($columns as $name => $index) {
            $data[$name] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    protected function mapRow(array $data, int $rowNumber): array
    {
        $text = $data['question'] ?? '';

        if ($text === '') {
            throw new InvalidArgumentException("Row {$rowNumber}: Question text is required.");
        }

        $type = $this->normalizeType($data['type'] ?? '');

        if ($type === null) {
            throw new InvalidArgumentException("Row {$rowNumber}: Unsupported question type \"{$data['type']}\".");
        }

        $points = $this->normalizePoints($data['points'] ?? '', $rowNumber);
        $difficulty = $this->normalizeDifficulty($data['difficulty'] ?? '');
        $topic = $data['topic'] ?? '';
        $sampleAnswer = $data['sample_answer'] ?? '';

        return match ($type) {
            'multiple_choice' => $this->mapMultipleChoice($data, $rowNumber, $text, $points, $difficulty, $topic),
            'true_false' => $this->mapTrueFalse($data, $rowNumber, $text, $points, $difficulty, $topic),
            'identification' => $this->mapIdentification($data, $rowNumber, $text, $points, $difficulty, $topic),
            'essay' => $this->mapEssay($data, $rowNumber, $text, $points, $difficulty, $topic, $sampleAnswer),
            default => throw new InvalidArgumentException("Row {$rowNumber}: Unsupported question type."),
        };
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    protected function mapMultipleChoice(array $data, int $rowNumber, string $text, int $points, string $difficulty, string $topic): array
    {
        $labels = ['A', 'B', 'C', 'D'];
        $choices = [];

        foreach ($labels as $label) {
            $choiceKey = 'choice_'.strtolower($label);
            $choices[] = [
                'id' => $label,
                'text' => $data[$choiceKey] ?? '',
            ];
        }

        $filledChoices = array_values(array_filter($choices, fn (array $choice) => $choice['text'] !== ''));

        if (count($filledChoices) < 2) {
            throw new InvalidArgumentException("Row {$rowNumber}: Multiple choice questions need at least two choices.");
        }

        $correctAnswer = $this->normalizeChoiceAnswer($data['correct_answer'] ?? '', $rowNumber, $labels);

        return [
            'type' => 'multiple_choice',
            'text' => $text,
            'choices' => $choices,
            'correctAnswer' => $correctAnswer,
            'sampleAnswer' => '',
            'points' => $points,
            'difficulty' => $difficulty,
            'topic' => $topic,
        ];
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    protected function mapTrueFalse(array $data, int $rowNumber, string $text, int $points, string $difficulty, string $topic): array
    {
        $correctAnswer = $this->normalizeTrueFalseAnswer($data['correct_answer'] ?? '', $rowNumber);

        return [
            'type' => 'true_false',
            'text' => $text,
            'choices' => [
                ['id' => 'true', 'text' => 'True'],
                ['id' => 'false', 'text' => 'False'],
            ],
            'correctAnswer' => $correctAnswer,
            'sampleAnswer' => '',
            'points' => $points,
            'difficulty' => $difficulty,
            'topic' => $topic,
        ];
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    protected function mapIdentification(array $data, int $rowNumber, string $text, int $points, string $difficulty, string $topic): array
    {
        $correctAnswer = trim($data['correct_answer'] ?? '');

        if ($correctAnswer === '') {
            throw new InvalidArgumentException("Row {$rowNumber}: Identification questions require a correct answer.");
        }

        return [
            'type' => 'identification',
            'text' => $text,
            'choices' => [],
            'correctAnswer' => $correctAnswer,
            'sampleAnswer' => '',
            'points' => $points,
            'difficulty' => $difficulty,
            'topic' => $topic,
        ];
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    protected function mapEssay(array $data, int $rowNumber, string $text, int $points, string $difficulty, string $topic, string $sampleAnswer): array
    {
        return [
            'type' => 'essay',
            'text' => $text,
            'choices' => [],
            'correctAnswer' => '',
            'sampleAnswer' => $sampleAnswer,
            'points' => $points,
            'difficulty' => $difficulty,
            'topic' => $topic,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function createQuestion(array $payload, int $subjectId, ?int $instructorId): Question
    {
        $question = Question::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'subject_id' => $subjectId,
            'instructor_id' => $instructorId,
            'type' => $payload['type'],
            'question_text' => $payload['text'],
            'correct_answer' => $this->dbCorrectAnswer($payload),
            'points' => $payload['points'],
            'explanation' => $payload['sampleAnswer'] ?: null,
            'difficulty' => strtolower($payload['difficulty']),
            'metadata' => filled($payload['topic']) ? ['topic' => $payload['topic']] : null,
        ]);

        $this->syncChoices($question, $payload);

        return $question;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function updateQuestion(int $questionId, array $payload, int $subjectId, ?int $instructorId): void
    {
        $question = Question::query()->findOrFail($questionId);

        $question->update([
            'subject_id' => $subjectId,
            'instructor_id' => $instructorId,
            'type' => $payload['type'],
            'question_text' => $payload['text'],
            'correct_answer' => $this->dbCorrectAnswer($payload),
            'points' => $payload['points'],
            'explanation' => $payload['sampleAnswer'] ?: null,
            'difficulty' => strtolower($payload['difficulty']),
            'metadata' => filled($payload['topic']) ? ['topic' => $payload['topic']] : null,
        ]);

        $question->choices()->delete();
        $this->syncChoices($question, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function syncChoices(Question $question, array $payload): void
    {
        if ($payload['type'] === 'multiple_choice') {
            foreach ($payload['choices'] as $index => $choice) {
                if ($choice['text'] === '') {
                    continue;
                }

                $question->choices()->create([
                    'label' => $choice['id'],
                    'choice_text' => $choice['text'],
                    'is_correct' => strtoupper((string) $payload['correctAnswer']) === strtoupper((string) $choice['id']),
                    'order' => $index,
                ]);
            }

            return;
        }

        if ($payload['type'] === 'true_false') {
            foreach ($payload['choices'] as $index => $choice) {
                $question->choices()->create([
                    'label' => $choice['id'],
                    'choice_text' => $choice['text'],
                    'is_correct' => (string) $payload['correctAnswer'] === (string) $choice['id'],
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
        return match ($payload['type']) {
            'multiple_choice' => [strtolower((string) $payload['correctAnswer'])],
            'true_false' => [(string) $payload['correctAnswer']],
            'identification' => [(string) $payload['correctAnswer']],
            default => null,
        };
    }

    protected function normalizeType(string $type): ?string
    {
        $normalized = strtolower(trim(str_replace([' ', '-'], '_', $type)));

        $aliases = [
            'mc' => 'multiple_choice',
            'multiplechoice' => 'multiple_choice',
            'multiple_select' => 'multiple_choice',
            'tf' => 'true_false',
            'truefalse' => 'true_false',
            'true_or_false' => 'true_false',
            'true/false' => 'true_false',
            'id' => 'identification',
            'short_answer' => 'identification',
        ];

        $normalized = $aliases[$normalized] ?? $normalized;

        return in_array($normalized, $this->supportedTypes, true) ? $normalized : null;
    }

    protected function normalizePoints(string $value, int $rowNumber): int
    {
        if ($value === '') {
            return 1;
        }

        if (! is_numeric($value) || (int) $value < 1) {
            throw new InvalidArgumentException("Row {$rowNumber}: Points must be a whole number of at least 1.");
        }

        return (int) $value;
    }

    protected function normalizeDifficulty(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'easy' => 'Easy',
            'medium', 'med' => 'Medium',
            'hard', 'difficult' => 'Hard',
            default => $value !== '' ? ucfirst($value) : 'Medium',
        };
    }

    /**
     * @param  list<string>  $labels
     */
    protected function normalizeChoiceAnswer(string $value, int $rowNumber, array $labels): string
    {
        $answer = strtoupper(trim($value));

        if ($answer === '') {
            throw new InvalidArgumentException("Row {$rowNumber}: Multiple choice questions require a correct answer (A, B, C, or D).");
        }

        if (! in_array($answer, $labels, true)) {
            throw new InvalidArgumentException("Row {$rowNumber}: Correct answer must be one of: ".implode(', ', $labels).'.');
        }

        return $answer;
    }

    protected function normalizeTrueFalseAnswer(string $value, int $rowNumber): string
    {
        $answer = strtolower(trim($value));

        return match ($answer) {
            'true', 't', 'yes', 'y', '1', 'a' => 'true',
            'false', 'f', 'no', 'n', '0', 'b' => 'false',
            default => throw new InvalidArgumentException("Row {$rowNumber}: True/false correct answer must be true or false."),
        };
    }

    /**
     * @param  list<string|null>  $row
     */
    protected function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
