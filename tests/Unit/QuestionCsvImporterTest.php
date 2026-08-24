<?php

namespace Tests\Unit;

use App\Services\Questions\QuestionCsvImporter;
use Tests\TestCase;

class QuestionCsvImporterTest extends TestCase
{
    private QuestionCsvImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new QuestionCsvImporter;
    }

    public function test_imports_supported_question_types(): void
    {
        $path = $this->writeCsv([
            ['question', 'type', 'choice_a', 'choice_b', 'choice_c', 'choice_d', 'correct_answer', 'points', 'difficulty', 'topic', 'sample_answer'],
            ['Which option is correct?', 'multiple_choice', 'Wrong', 'Correct', 'Also wrong', 'Nope', 'B', '2', 'easy', 'Basics', ''],
            ['The sky is blue.', 'true_false', '', '', '', '', 'true', '1', 'medium', 'Science', ''],
            ['Name the CPU acronym expansion component.', 'identification', '', '', '', '', 'Central Processing Unit', '1', 'hard', 'Hardware', ''],
            ['Describe cloud computing.', 'essay', '', '', '', '', '', '5', 'medium', 'Cloud', 'Look for scalability and on-demand access.'],
        ]);

        $result = $this->importer->import($path);

        $this->assertSame(4, $result['imported']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('multiple_choice', $result['questions'][0]['type']);
        $this->assertSame('B', $result['questions'][0]['correctAnswer']);
        $this->assertSame('true_false', $result['questions'][1]['type']);
        $this->assertSame('identification', $result['questions'][2]['type']);
        $this->assertSame('essay', $result['questions'][3]['type']);
        $this->assertSame('Look for scalability and on-demand access.', $result['questions'][3]['sampleAnswer']);
    }

    public function test_reports_row_errors_without_importing_invalid_rows(): void
    {
        $path = $this->writeCsv([
            ['question', 'type', 'correct_answer'],
            ['Valid question', 'identification', 'answer'],
            ['', 'identification', 'answer'],
            ['Bad type', 'matching', 'x'],
        ]);

        $result = $this->importer->import($path);

        $this->assertSame(1, $result['imported']);
        $this->assertCount(2, $result['errors']);
    }

    public function test_template_includes_example_rows(): void
    {
        $template = $this->importer->template();

        $this->assertStringContainsString('question,type,choice_a', $template);
        $this->assertStringContainsString('multiple_choice', $template);
        $this->assertStringContainsString('essay', $template);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'question-import-');
        $handle = fopen($path, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
