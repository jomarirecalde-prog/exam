<?php

namespace Tests\Feature;

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
