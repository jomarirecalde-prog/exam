<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Enums\ResultStatus;
use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExaminationVersion;
use App\Models\Grade;
use App\Models\Instructor;
use App\Models\Question;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualGradingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('instructor');
        Role::findOrCreate('student');
    }

    public function test_instructor_can_grade_pending_essay_answer(): void
    {
        [$instructorUser, $exam, $student, $essayAnswer] = $this->createPendingEssaySubmission();

        $response = $this->actingAs($instructorUser)->post(route('examinations.answers.grade', [
            'examination' => $exam,
            'answer' => $essayAnswer,
        ]), [
            'points_earned' => 4,
            'feedback' => 'Strong explanation with clear examples.',
        ]);

        $response->assertRedirect(route('examinations.result', [
            'examination' => $exam,
            'student' => $student->id,
        ]));
        $response->assertSessionHas('status');

        $essayAnswer->refresh();
        $this->assertTrue($essayAnswer->is_graded);
        $this->assertSame('4.00', $essayAnswer->points_earned);
        $this->assertSame('Strong explanation with clear examples.', $essayAnswer->essayAnswer?->feedback);

        $grade = Grade::query()
            ->where('examination_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        $this->assertSame(ResultStatus::Passed, $grade->status);
        $this->assertSame('5.00', $grade->raw_score);
    }

    public function test_grading_page_shows_grade_form_for_staff(): void
    {
        [$instructorUser, $exam, $student] = $this->createPendingEssaySubmission();

        $response = $this->actingAs($instructorUser)->get(route('examinations.result', [
            'examination' => $exam,
            'student' => $student->id,
        ]));

        $response->assertOk();
        $response->assertSee('Grade this answer');
        $response->assertSee('Save Grade');
        $response->assertSee('answers waiting for manual grading');
    }

    public function test_student_cannot_grade_answers(): void
    {
        [, $exam, $student, $essayAnswer] = $this->createPendingEssaySubmission();
        $studentUser = $student->user;

        $response = $this->actingAs($studentUser)->post(route('examinations.answers.grade', [
            'examination' => $exam,
            'answer' => $essayAnswer,
        ]), [
            'points_earned' => 4,
        ]);

        $response->assertForbidden();
    }

    public function test_other_instructor_cannot_grade_answers(): void
    {
        [, $exam,, $essayAnswer] = $this->createPendingEssaySubmission();

        $otherInstructorUser = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $otherInstructorUser->assignRole('instructor');
        Instructor::create([
            'user_id' => $otherInstructorUser->id,
            'employee_id' => 'EMP-OTHER',
        ]);

        $response = $this->actingAs($otherInstructorUser)->post(route('examinations.answers.grade', [
            'examination' => $exam,
            'answer' => $essayAnswer,
        ]), [
            'points_earned' => 4,
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_award_more_points_than_question_allows(): void
    {
        [$instructorUser, $exam,, $essayAnswer] = $this->createPendingEssaySubmission();

        $response = $this->actingAs($instructorUser)->post(route('examinations.answers.grade', [
            'examination' => $exam,
            'answer' => $essayAnswer,
        ]), [
            'points_earned' => 99,
        ]);

        $response->assertSessionHasErrors('points_earned');
        $this->assertFalse($essayAnswer->fresh()->is_graded);
    }

    /**
     * @return array{0: User, 1: Examination, 2: Student, 3: StudentAnswer}
     */
    protected function createPendingEssaySubmission(): array
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

        $instructorUser = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $instructorUser->assignRole('instructor');
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'employee_id' => 'EMP-1001',
        ]);

        $studentUser = User::factory()->create(['email_verified_at' => now(), 'name' => 'Jane Student', 'is_active' => true]);
        $studentUser->assignRole('student');
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'STU-1001',
            'section_id' => $section->id,
        ]);

        $exam = Examination::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'instructor_id' => $instructor->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'examination_period' => 'MIDTERM',
            'title' => 'Essay Exam',
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'status' => ExamStatus::Closed,
        ]);

        $exam->sections()->sync([$section->id]);

        $mcQuestion = Question::create([
            'uuid' => (string) Str::uuid(),
            'type' => QuestionType::MultipleChoice,
            'question_text' => 'Sample MC question',
            'correct_answer' => 'a',
            'points' => 1,
        ]);

        $essayQuestion = Question::create([
            'uuid' => (string) Str::uuid(),
            'type' => QuestionType::Essay,
            'question_text' => 'Explain the role of information systems.',
            'points' => 4,
        ]);

        $version = ExaminationVersion::create([
            'examination_id' => $exam->id,
            'version_number' => 1,
        ]);

        $attempt = ExaminationAttempt::create([
            'uuid' => (string) Str::uuid(),
            'examination_id' => $exam->id,
            'examination_version_id' => $version->id,
            'student_id' => $student->id,
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
        ]);

        StudentAnswer::create([
            'uuid' => (string) Str::uuid(),
            'examination_attempt_id' => $attempt->id,
            'question_id' => $mcQuestion->id,
            'answer' => ['value' => 'a'],
            'is_correct' => true,
            'points_earned' => 1,
            'requires_manual_grading' => false,
            'is_graded' => true,
        ]);

        $essayAnswer = StudentAnswer::create([
            'uuid' => (string) Str::uuid(),
            'examination_attempt_id' => $attempt->id,
            'question_id' => $essayQuestion->id,
            'answer' => ['value' => 'This is my essay response.'],
            'requires_manual_grading' => true,
            'is_graded' => false,
            'points_earned' => 0,
        ]);

        Grade::create([
            'examination_attempt_id' => $attempt->id,
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'raw_score' => 1,
            'total_points' => 5,
            'percentage' => 20,
            'status' => ResultStatus::PendingGrading,
            'passed' => false,
            'is_released' => true,
            'released_at' => now(),
        ]);

        return [$instructorUser, $exam, $student, $essayAnswer];
    }
}
