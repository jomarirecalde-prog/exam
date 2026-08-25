<?php

namespace Tests\Unit;

use App\Enums\QuestionType;
use App\Enums\ResultStatus;
use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExaminationVersion;
use App\Models\Instructor;
use App\Models\Question;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use App\Services\Grading\GradingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GradingEngineTest extends TestCase
{
    use RefreshDatabase;

    private GradingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new GradingEngine;
    }

    public function test_perfect_score_on_multiple_choice(): void
    {
        $question = $this->makeQuestion(QuestionType::MultipleChoice, 'b', 2);
        $attempt = $this->makeAttempt($question, 'b');

        $result = $this->engine->gradeAttempt($attempt);

        $this->assertSame(2.0, $result->earnedPoints);
        $this->assertSame(100.0, $result->percentage);
        $this->assertTrue($result->passed);
        $this->assertSame(ResultStatus::Passed, $result->status);
    }

    public function test_zero_score_on_incorrect_answer(): void
    {
        $question = $this->makeQuestion(QuestionType::MultipleChoice, 'b', 1);
        $attempt = $this->makeAttempt($question, 'a');

        $result = $this->engine->gradeAttempt($attempt);

        $this->assertSame(0.0, $result->earnedPoints);
        $this->assertFalse($result->passed);
    }

    public function test_partial_score_with_two_questions(): void
    {
        $examContext = $this->createExamContext();
        $q1 = $this->makeQuestion(QuestionType::TrueFalse, 'true', 1);
        $q2 = $this->makeQuestion(QuestionType::Identification, 'cpu', 1);

        $attempt = ExaminationAttempt::create([
            'uuid' => (string) Str::uuid(),
            'examination_id' => $examContext['exam']->id,
            'examination_version_id' => $examContext['version']->id,
            'student_id' => $examContext['student']->id,
            'status' => 'SUBMITTED',
        ]);

        StudentAnswer::create([
            'uuid' => (string) Str::uuid(),
            'examination_attempt_id' => $attempt->id,
            'question_id' => $q1->id,
            'answer' => 'true',
        ]);

        StudentAnswer::create([
            'uuid' => (string) Str::uuid(),
            'examination_attempt_id' => $attempt->id,
            'question_id' => $q2->id,
            'answer' => 'wrong',
        ]);

        $result = $this->engine->gradeAttempt($attempt->load(['answers.question', 'examination']));

        $this->assertSame(1.0, $result->earnedPoints);
        $this->assertSame(50.0, $result->percentage);
    }

    public function test_multiple_select_requires_exact_match(): void
    {
        $question = $this->makeQuestion(QuestionType::MultipleSelect, ['a', 'c'], 2);

        $this->assertTrue($this->engine->evaluateObjectiveAnswer($question, ['a', 'c']));
        $this->assertFalse($this->engine->evaluateObjectiveAnswer($question, ['a', 'b']));
    }

    public function test_essay_questions_mark_pending_grading(): void
    {
        $question = $this->makeQuestion(QuestionType::Essay, null, 5);
        $attempt = $this->makeAttempt($question, 'Sample essay response');

        $result = $this->engine->gradeAttempt($attempt);

        $this->assertTrue($result->requiresManualGrading);
        $this->assertSame(ResultStatus::PendingGrading, $result->status);
    }

    public function test_decimal_points_are_respected(): void
    {
        $question = $this->makeQuestion(QuestionType::MultipleChoice, 'a', 1.5);
        $attempt = $this->makeAttempt($question, 'a');

        $result = $this->engine->gradeAttempt($attempt);

        $this->assertSame(1.5, $result->earnedPoints);
    }

    public function test_missing_answer_scores_zero(): void
    {
        $question = $this->makeQuestion(QuestionType::MultipleChoice, 'a', 1);
        $attempt = $this->makeAttempt($question, null);

        $result = $this->engine->gradeAttempt($attempt);

        $this->assertSame(0.0, $result->earnedPoints);
    }

    public function test_apply_to_attempt_releases_fully_graded_results(): void
    {
        $question = $this->makeQuestion(QuestionType::MultipleChoice, 'a', 1);
        $attempt = $this->makeAttempt($question, 'a');

        $grade = $this->engine->applyToAttempt($attempt);

        $this->assertTrue($grade->is_released);
        $this->assertNotNull($grade->released_at);
        $this->assertSame(ResultStatus::Passed, $grade->status);
    }

    public function test_apply_to_attempt_releases_pending_manual_grading(): void
    {
        $question = $this->makeQuestion(QuestionType::Essay, null, 5);
        $attempt = $this->makeAttempt($question, 'Sample essay response');

        $grade = $this->engine->applyToAttempt($attempt);

        $this->assertTrue($grade->is_released);
        $this->assertNotNull($grade->released_at);
        $this->assertSame(ResultStatus::PendingGrading, $grade->status);
    }

    protected function makeQuestion(QuestionType $type, mixed $correct, float $points): Question
    {
        return Question::create([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'question_text' => 'Test question',
            'correct_answer' => $correct,
            'points' => $points,
        ]);
    }

    protected function makeAttempt(Question $question, mixed $answer): ExaminationAttempt
    {
        $examContext = $this->createExamContext();

        $attempt = ExaminationAttempt::create([
            'uuid' => (string) Str::uuid(),
            'examination_id' => $examContext['exam']->id,
            'examination_version_id' => $examContext['version']->id,
            'student_id' => $examContext['student']->id,
            'status' => 'SUBMITTED',
        ]);

        if ($answer !== null) {
            StudentAnswer::create([
                'uuid' => (string) Str::uuid(),
                'examination_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'answer' => $answer,
            ]);
        }

        return $attempt->load(['answers.question', 'examination']);
    }

    protected function createExamContext(): array
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester = Semester::create(['academic_year_id' => $year->id, 'name' => '1st Semester']);
        $subject = Subject::create(['code' => 'IS101', 'name' => 'IS 101']);

        $department = \App\Models\Department::create(['code' => 'CCIS', 'name' => 'CCIS']);
        $program = \App\Models\Program::create(['department_id' => $department->id, 'code' => 'BSIS', 'name' => 'BSIS']);
        $yearLevel = \App\Models\YearLevel::create(['program_id' => $program->id, 'name' => '1st Year', 'level' => 1]);

        $section = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 1A',
        ]);

        $user = User::factory()->create();
        $instructor = Instructor::create(['user_id' => $user->id, 'employee_id' => 'EMP-001']);
        $student = Student::create(['user_id' => User::factory()->create()->id, 'student_id' => 'STU-001']);

        $exam = Examination::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'instructor_id' => $instructor->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'examination_period' => 'MIDTERM',
            'title' => 'Test Exam',
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'status' => 'DRAFT',
        ]);

        $version = ExaminationVersion::create([
            'examination_id' => $exam->id,
            'version_number' => 1,
        ]);

        return compact('exam', 'version', 'student');
    }
}
