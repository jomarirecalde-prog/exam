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

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('student');
    }

    public function test_student_sees_only_their_released_results_on_results_page(): void
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

        $released = $this->createGradeForStudent($student, released: true, suffix: 'released');
        $this->createGradeForStudent($student, released: false, suffix: 'pending');
        $this->createGradeForStudent($otherStudent, released: true, suffix: 'other');

        $response = $this->actingAs($studentUser)->get(route('results.index'));

        $response->assertOk();
        $response->assertViewHas('grades', fn ($grades) => $grades->count() === 1 && $grades->first()->is($released));
    }

    protected function createGradeForStudent(Student $student, bool $released, string $suffix): Grade
    {
        static $year;
        static $semester;
        static $subject;
        static $section;
        static $instructor;

        $year ??= AcademicYear::create(['name' => '2026-2027', 'is_current' => true]);
        $semester ??= Semester::create(['academic_year_id' => $year->id, 'name' => '1st Semester']);
        $subject ??= Subject::create(['code' => 'IS101', 'name' => 'IS 101']);

        if (! isset($section)) {
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
        }

        $instructor ??= Instructor::create([
            'user_id' => User::factory()->create()->id,
            'employee_id' => 'EMP-9001',
        ]);

        $exam = Examination::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'instructor_id' => $instructor->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
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
            'is_released' => $released,
            'released_at' => $released ? now() : null,
        ]);
    }
}
