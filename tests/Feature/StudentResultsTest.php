<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Enums\ResultStatus;
use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExaminationVersion;
use App\Models\EssayAnswer;
use App\Models\Grade;
use App\Models\Instructor;
use App\Models\Question;
use App\Models\QuestionChoice;
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

class StudentResultsTest extends TestCase
{
    use RefreshDatabase;

    private ?AcademicYear $gradeYear = null;

    private ?Semester $gradeSemester = null;

    private ?Subject $gradeSubject = null;

    private ?Section $gradeSection = null;

    private ?Instructor $gradeInstructor = null;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('student');
    }

    public function test_student_sees_all_of_their_results_on_results_page(): void
    {
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('student');
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'STU-9001',
        ]);

        $otherStudent = Student::create([
            'user_id' => User::factory()->create()->id,
            'student_id' => 'STU-9002',
        ]);

        $this->createGradeForStudent($student, suffix: 'first');
        $this->createGradeForStudent($student, suffix: 'second');
        $this->createGradeForStudent($otherStudent, suffix: 'other');

        $response = $this->actingAs($studentUser)->get(route('results.index'));

        $response->assertOk();
        $response->assertViewHas('grades', fn ($grades) => $grades->count() === 2);
    }

    public function test_student_can_view_examination_result_detail_page(): void
    {
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('student');
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'STU-9001',
        ]);

        $grade = $this->createGradeForStudent($student, suffix: 'detail');

        $response = $this->actingAs($studentUser)->get(route('examinations.result', $grade->examination_id));

        $response->assertOk();
        $response->assertSee('Test Exam detail');
        $response->assertSee('80%');
    }

    public function test_student_sees_answer_review_while_pending_manual_grading(): void
    {
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('student');
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'STU-9001',
        ]);

        $grade = $this->createPendingGradingGrade($student);

        $response = $this->actingAs($studentUser)->get(route('examinations.result', $grade->examination_id));

        $response->assertOk();
        $response->assertSee('Answer Review');
        $response->assertSee('Correct');
        $response->assertSee('Pending review');
        $response->assertSee('Your answer');
        $response->assertSee('People, processes, and technology working together');
        $response->assertSee('This is my essay response.');
        $response->assertDontSee('Results are not available yet.');
    }

    public function test_student_sees_graded_essay_points_on_result_page(): void
    {
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('student');
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => 'STU-9001',
        ]);

        $grade = $this->createPendingGradingGrade($student, essayGraded: true);

        $response = $this->actingAs($studentUser)->get(route('examinations.result', $grade->examination_id));

        $response->assertOk();
        $response->assertSee('Graded');
        $response->assertSee('4 / 5 pts');
        $response->assertSee('Good structure and examples.');
    }

    protected function createPendingGradingGrade(Student $student, bool $essayGraded = false): Grade
    {
        $this->gradeYear ??= AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $this->gradeSemester ??= Semester::create(['academic_year_id' => $this->gradeYear->id, 'name' => '1st Semester']);
        $this->gradeSubject ??= Subject::create(['code' => 'IS101', 'name' => 'IS 101']);

        if ($this->gradeSection === null) {
            $department = \App\Models\Department::create(['code' => 'CCIS', 'name' => 'CCIS']);
            $program = \App\Models\Program::create(['department_id' => $department->id, 'code' => 'BSIS', 'name' => 'BSIS']);
            $yearLevel = \App\Models\YearLevel::create(['program_id' => $program->id, 'name' => '1st Year', 'level' => 1]);

            $this->gradeSection = Section::create([
                'program_id' => $program->id,
                'year_level_id' => $yearLevel->id,
                'academic_year_id' => $this->gradeYear->id,
                'semester_id' => $this->gradeSemester->id,
                'name' => 'BSIS 1A',
            ]);
        }

        $this->gradeInstructor ??= Instructor::create([
            'user_id' => User::factory()->create()->id,
            'employee_id' => 'EMP-9001',
        ]);

        $exam = Examination::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $this->gradeSubject->id,
            'section_id' => $this->gradeSection->id,
            'instructor_id' => $this->gradeInstructor->id,
            'academic_year_id' => $this->gradeYear->id,
            'semester_id' => $this->gradeSemester->id,
            'examination_period' => 'MIDTERM',
            'title' => 'Mixed Grading Exam',
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'status' => 'PUBLISHED',
        ]);

        $mcQuestion = Question::create([
            'uuid' => (string) Str::uuid(),
            'type' => QuestionType::MultipleChoice,
            'question_text' => 'Which best describes an information system?',
            'correct_answer' => 'B',
            'points' => 1,
        ]);

        QuestionChoice::create([
            'question_id' => $mcQuestion->id,
            'label' => 'A',
            'choice_text' => 'A collection of hardware only',
            'is_correct' => false,
            'order' => 0,
        ]);
        QuestionChoice::create([
            'question_id' => $mcQuestion->id,
            'label' => 'B',
            'choice_text' => 'People, processes, and technology working together',
            'is_correct' => true,
            'order' => 1,
        ]);

        $essayQuestion = Question::create([
            'uuid' => (string) Str::uuid(),
            'type' => QuestionType::Essay,
            'question_text' => 'Explain the role of information systems.',
            'points' => 5,
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
            'answer' => ['value' => 'B'],
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
            'is_graded' => $essayGraded,
            'points_earned' => $essayGraded ? 4 : 0,
        ]);

        EssayAnswer::create([
            'student_answer_id' => $essayAnswer->id,
            'answer_text' => 'This is my essay response.',
            'score' => $essayGraded ? 4 : null,
            'feedback' => $essayGraded ? 'Good structure and examples.' : null,
            'graded_at' => $essayGraded ? now() : null,
        ]);

        return Grade::create([
            'examination_attempt_id' => $attempt->id,
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'raw_score' => $essayGraded ? 5 : 1,
            'total_points' => 6,
            'percentage' => $essayGraded ? 83.33 : 16.67,
            'status' => $essayGraded ? ResultStatus::Passed : ResultStatus::PendingGrading,
            'passed' => $essayGraded,
            'is_released' => true,
            'released_at' => now(),
        ]);
    }

    protected function createGradeForStudent(Student $student, string $suffix): Grade
    {
        $this->gradeYear ??= AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $this->gradeSemester ??= Semester::create(['academic_year_id' => $this->gradeYear->id, 'name' => '1st Semester']);
        $this->gradeSubject ??= Subject::create(['code' => 'IS101', 'name' => 'IS 101']);

        if ($this->gradeSection === null) {
            $department = \App\Models\Department::create(['code' => 'CCIS', 'name' => 'CCIS']);
            $program = \App\Models\Program::create(['department_id' => $department->id, 'code' => 'BSIS', 'name' => 'BSIS']);
            $yearLevel = \App\Models\YearLevel::create(['program_id' => $program->id, 'name' => '1st Year', 'level' => 1]);

            $this->gradeSection = Section::create([
                'program_id' => $program->id,
                'year_level_id' => $yearLevel->id,
                'academic_year_id' => $this->gradeYear->id,
                'semester_id' => $this->gradeSemester->id,
                'name' => 'BSIS 1A',
            ]);
        }

        $this->gradeInstructor ??= Instructor::create([
            'user_id' => User::factory()->create()->id,
            'employee_id' => 'EMP-9001',
        ]);

        $exam = Examination::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $this->gradeSubject->id,
            'section_id' => $this->gradeSection->id,
            'instructor_id' => $this->gradeInstructor->id,
            'academic_year_id' => $this->gradeYear->id,
            'semester_id' => $this->gradeSemester->id,
            'examination_period' => 'MIDTERM',
            'title' => 'Test Exam '.$suffix,
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'status' => 'PUBLISHED',
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

        return Grade::create([
            'examination_attempt_id' => $attempt->id,
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'raw_score' => 8,
            'total_points' => 10,
            'percentage' => 80,
            'status' => ResultStatus::Passed,
            'passed' => true,
            'is_released' => true,
            'released_at' => now(),
        ]);
    }
}
