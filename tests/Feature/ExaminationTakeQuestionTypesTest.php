<?php

namespace Tests\Feature;

use App\Enums\ExaminationAccessMode;
use App\Enums\ExaminationPeriod;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationQuestion;
use App\Models\ExaminationVersion;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExaminationTakeQuestionTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Student->value);
        Role::findOrCreate(UserRole::Instructor->value);
    }

    public function test_take_page_serializes_mixed_question_types_without_fake_choices_for_essay(): void
    {
        ['studentUser' => $studentUser, 'examination' => $examination] = $this->scenarioWithQuestions();

        $response = $this->actingAs($studentUser)
            ->get(route('examinations.take', $examination))
            ->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString('\u0022type\u0022:\u0022essay\u0022', $content);
        $this->assertStringContainsString('\u0022type\u0022:\u0022multiple_choice\u0022', $content);
        $this->assertStringContainsString('\u0022type\u0022:\u0022true_false\u0022', $content);
        $this->assertStringContainsString('\u0022type\u0022:\u0022identification\u0022', $content);
        $this->assertStringContainsString('Explain database normalization.', $content);
        $this->assertStringNotContainsString('Option A', $content);
        $this->assertStringContainsString('Write your answer here...', $content);
        $this->assertStringContainsString('questionType(question) === \'essay\'', $content);
    }

    public function test_student_can_save_essay_answer_as_text(): void
    {
        ['studentUser' => $studentUser, 'examination' => $examination, 'essayQuestion' => $essayQuestion] = $this->scenarioWithQuestions();

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.accept-policy', $examination))
            ->assertOk();

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.start', $examination))
            ->assertOk();

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.answers.bulk', $examination), [
                'answers' => [[
                    'question_id' => $essayQuestion->id,
                    'answer' => 'Normalization reduces redundancy in relational schemas.',
                    'is_flagged' => false,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('student_answers', [
            'question_id' => $essayQuestion->id,
        ]);

        $stored = \App\Models\StudentAnswer::query()
            ->where('question_id', $essayQuestion->id)
            ->first();

        $this->assertSame(
            'Normalization reduces redundancy in relational schemas.',
            $stored->answer['value'] ?? $stored->answer
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function scenarioWithQuestions(): array
    {
        $year = AcademicYear::create(['name' => '2026-2027', 'is_current' => true, 'is_active' => true]);
        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'First Semester',
            'order' => 1,
            'is_current' => true,
            'is_active' => true,
        ]);
        $department = Department::create(['code' => 'CCIS', 'name' => 'Computing', 'is_active' => true]);
        $program = Program::create(['department_id' => $department->id, 'code' => 'BSIS', 'name' => 'BSIS', 'is_active' => true]);
        $yearLevel = YearLevel::create(['program_id' => $program->id, 'name' => '1st Year', 'level' => 1, 'is_active' => true]);
        $section = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 1A',
            'code' => 'BSIS-1A',
            'is_active' => true,
        ]);
        $subject = Subject::create(['department_id' => $department->id, 'code' => 'IS101', 'name' => 'IS', 'units' => 3, 'is_active' => true]);

        $instructorUser = User::factory()->create(['is_active' => true]);
        $instructorUser->assignRole(UserRole::Instructor->value);
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'employee_id' => 'EMP-001',
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $studentUser = User::factory()->create(['is_active' => true]);
        $studentUser->assignRole(UserRole::Student->value);
        Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'STU-001',
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'section_id' => $section->id,
            'is_active' => true,
        ]);

        $examination = Examination::create([
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'examination_period' => ExaminationPeriod::Midterm,
            'title' => 'Mixed Types Exam',
            'duration_minutes' => 60,
            'status' => ExamStatus::Published,
            'access_mode' => ExaminationAccessMode::SubjectAndSections,
            'needs_section_review' => false,
            'current_version' => 1,
        ]);
        $examination->sections()->attach($section->id);

        $version = ExaminationVersion::create([
            'examination_id' => $examination->id,
            'version_number' => 1,
        ]);

        $mc = Question::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'type' => QuestionType::MultipleChoice,
            'question_text' => 'Choose the best answer.',
            'points' => 1,
        ]);
        QuestionChoice::create(['question_id' => $mc->id, 'label' => 'A', 'choice_text' => 'Alpha', 'is_correct' => true, 'order' => 0]);

        $essay = Question::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'type' => QuestionType::Essay,
            'question_text' => 'Explain database normalization.',
            'points' => 5,
        ]);
        QuestionChoice::create(['question_id' => $essay->id, 'label' => 'A', 'choice_text' => 'Legacy', 'is_correct' => false, 'order' => 0]);

        $tf = Question::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'type' => QuestionType::TrueFalse,
            'question_text' => 'SQL is a programming language.',
            'points' => 1,
        ]);
        foreach (['true' => 'True', 'false' => 'False'] as $id => $text) {
            QuestionChoice::create([
                'question_id' => $tf->id,
                'label' => $id,
                'choice_text' => $text,
                'is_correct' => $id === 'false',
                'order' => $id === 'true' ? 0 : 1,
            ]);
        }

        $identification = Question::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'type' => QuestionType::Identification,
            'question_text' => 'What does CPU stand for?',
            'correct_answer' => ['central processing unit'],
            'points' => 1,
        ]);

        foreach ([$mc, $essay, $tf, $identification] as $index => $question) {
            ExaminationQuestion::create([
                'examination_id' => $examination->id,
                'examination_version_id' => $version->id,
                'question_id' => $question->id,
                'order' => $index + 1,
            ]);
        }

        return [
            'studentUser' => $studentUser,
            'examination' => $examination,
            'essayQuestion' => $essay,
        ];
    }
}
