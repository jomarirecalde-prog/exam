<?php

namespace App\Services\Questions;

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
     * @return array{questions: list<array<string, mixed>>, errors: list<string>, warnings: list<string>, imported: int}
     */
    public function import(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('Unable to read the uploaded CSV file.');
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [
                'questions' => [],
                'errors' => ['The CSV file is empty.'],
                'warnings' => [],
                'imported' => 0,
            ];
        }

        $columns = $this->normalizeHeader($header);
        $missing = array_diff(['question', 'type'], array_keys($columns));

        if ($missing !== []) {
            fclose($handle);

            return [
                'questions' => [],
                'errors' => ['Missing required column(s): '.implode(', ', $missing).'.'],
                'warnings' => [],
                'imported' => 0,
            ];
        }

        $questions = [];
        $errors = [];
        $warnings = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isBlankRow($row)) {
                continue;
            }

            $data = $this->rowToAssoc($columns, $row);

            try {
                $questions[] = $this->mapRow($data, $rowNumber);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        fclose($handle);

        if ($questions === [] && $errors === []) {
            $errors[] = 'No question rows were found in the CSV file.';
        }

        return [
            'questions' => $questions,
            'errors' => $errors,
            'warnings' => $warnings,
            'imported' => count($questions),
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

        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
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
