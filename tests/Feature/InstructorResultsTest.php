<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\ResultStatus;
use App\Models\AcademicYear;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExaminationVersion;
use App\Models\Grade;
use App\Models\Instructor;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorResultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('instructor');
        Role::findOrCreate('student');
    }

    public function test_instructor_sees_completed_examinations_on_results_page(): void
    {
        [$instructorUser, $exam] = $this->createExamWithSubmission(asInstructor: true);

        $response = $this->actingAs($instructorUser)->get(route('results.index'));

        $response->assertOk();
        $response->assertSee('Test Completed Exam');
        $response->assertSee('View Results');
    }

    public function test_instructor_can_view_student_results_for_selected_examination(): void
    {
        [$instructorUser, $exam, $student] = $this->createExamWithSubmission(asInstructor: true);

        $response = $this->actingAs($instructorUser)->get(route('results.show', $exam));

        $response->assertOk();
        $response->assertSee('Test Completed Exam');
        $response->assertSee($student->user->name);
        $response->assertSee('85%');
        $response->assertSee('View Details');
    }

    public function test_instructor_can_view_individual_student_result_detail(): void
    {
        [$instructorUser, $exam, $student] = $this->createExamWithSubmission(asInstructor: true);

        $response = $this->actingAs($instructorUser)->get(route('examinations.result', [
            'examination' => $exam,
            'student' => $student->id,
        ]));

        $response->assertOk();
        $response->assertSee('Test Completed Exam');
        $response->assertSee($student->user->name);
        $response->assertSee('85%');
        $response->assertSee('Back to Results');
    }

    public function test_instructor_cannot_view_results_for_other_instructors_examination(): void
    {
        [, $exam] = $this->createExamWithSubmission(asInstructor: true);

        $otherInstructorUser = User::factory()->create(['email_verified_at' => now()]);
        $otherInstructorUser->assignRole('instructor');
        Instructor::create([
            'user_id' => $otherInstructorUser->id,
            'employee_id' => 'EMP-OTHER',
        ]);

        $response = $this->actingAs($otherInstructorUser)->get(route('results.show', $exam));

        $response->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Examination, 2: Student}
     */
    protected function createExamWithSubmission(bool $asInstructor = false): array
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

        $instructorUser = User::factory()->create(['email_verified_at' => now()]);
        $instructorUser->assignRole('instructor');
        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'employee_id' => 'EMP-1001',
        ]);

        $studentUser = User::factory()->create(['email_verified_at' => now(), 'name' => 'Jane Student']);
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
            'title' => 'Test Completed Exam',
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'status' => ExamStatus::Closed,
        ]);

        $exam->sections()->sync([$section->id]);

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

        Grade::create([
            'examination_attempt_id' => $attempt->id,
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'raw_score' => 17,
            'total_points' => 20,
            'percentage' => 85,
            'status' => ResultStatus::Passed,
            'passed' => true,
            'is_released' => true,
            'released_at' => now(),
        ]);

        return [$instructorUser, $exam, $student];
    }
}
