<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamDeadlinePolicy;
use App\Enums\ExamEndPolicy;
use App\Enums\ExaminationAccessMode;
use App\Enums\ExaminationPeriod;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExaminationSetting;
use App\Models\ExaminationVersion;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\User;
use App\Models\YearLevel;
use App\Enums\StudentSubjectEnrollmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExaminationScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Superadmin->value);
        Role::findOrCreate(UserRole::Admin->value);
        Role::findOrCreate(UserRole::Instructor->value);
        Role::findOrCreate(UserRole::Student->value);
    }

    public function test_instructor_can_create_exam_with_immediate_availability(): void
    {
        $data = $this->scenario();

        $response = $this->actingAs($data['instructorUser'])->postJson(route('examinations.store'), [
            ...$this->examPayload($data),
            'availability_immediate' => true,
            'deadline_date' => now()->addDay()->format('Y-m-d'),
            'deadline_time' => '17:00',
            'deadline_policy' => ExamDeadlinePolicy::AllowActiveFinish->value,
            'status' => ExamStatus::Published->value,
        ]);

        $response->assertOk();

        $exam = Examination::query()->first();
        $this->assertNotNull($exam->available_from);
        $this->assertNotNull($exam->deadline_at);
        $this->assertEquals(ExamStatus::Active, $exam->status);
    }

    public function test_instructor_can_schedule_future_exam(): void
    {
        $data = $this->scenario();
        $start = now()->addDay();

        $response = $this->actingAs($data['instructorUser'])->postJson(route('examinations.store'), [
            ...$this->examPayload($data),
            'availability_immediate' => false,
            'available_from_date' => $start->format('Y-m-d'),
            'available_from_time' => $start->format('H:i'),
            'deadline_date' => $start->copy()->addHours(8)->format('Y-m-d'),
            'deadline_time' => $start->copy()->addHours(8)->format('H:i'),
            'deadline_policy' => ExamDeadlinePolicy::StopAll->value,
            'status' => ExamStatus::Published->value,
        ]);

        $response->assertOk();

        $exam = Examination::query()->first();
        $this->assertEquals(ExamStatus::Scheduled, $exam->status);
    }

    public function test_student_cannot_start_before_availability(): void
    {
        $data = $this->scenarioWithPublishedExam(
            availableFrom: now()->addHour(),
            deadlineAt: now()->addDay(),
        );

        $this->actingAs($data['studentUser'])
            ->get(route('examinations.take', $data['examination']))
            ->assertForbidden();
    }

    public function test_student_can_start_during_availability_period(): void
    {
        $data = $this->scenarioWithPublishedExam(
            availableFrom: now()->subHour(),
            deadlineAt: now()->addDay(),
        );

        $this->actingAs($data['studentUser'])
            ->get(route('examinations.take', $data['examination']))
            ->assertOk();
    }

    public function test_student_cannot_start_after_deadline(): void
    {
        $data = $this->scenarioWithPublishedExam(
            availableFrom: now()->subDays(2),
            deadlineAt: now()->subHour(),
        );

        $this->actingAs($data['studentUser'])
            ->get(route('examinations.take', $data['examination']))
            ->assertForbidden();
    }

    public function test_instructor_can_end_examination(): void
    {
        $data = $this->scenarioWithPublishedExam(
            availableFrom: now()->subHour(),
            deadlineAt: now()->addDay(),
        );

        $attempt = ExaminationAttempt::create([
            'uuid' => (string) Str::uuid(),
            'examination_id' => $data['examination']->id,
            'examination_version_id' => $data['version']->id,
            'student_id' => $data['student']->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now()->subMinutes(10),
            'expires_at' => now()->addMinutes(50),
            'duration_seconds' => 3600,
            'policy_accepted_at' => now()->subMinutes(11),
        ]);

        $response = $this->actingAs($data['instructorUser'])->postJson(
            route('monitoring.end', $data['examination']),
            [
                'end_policy' => ExamEndPolicy::AutoSubmit->value,
                'reason' => 'Class schedule has ended.',
            ],
        );

        $response->assertOk()->assertJsonPath('affected_students', 1);

        $data['examination']->refresh();
        $attempt->refresh();

        $this->assertEquals(ExamStatus::Ended, $data['examination']->status);
        $this->assertEquals(AttemptStatus::AutoSubmitted, $attempt->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'end_examination',
            'module' => 'examinations',
            'record_id' => $data['examination']->id,
        ]);
    }

    public function test_unauthorized_instructor_cannot_end_examination(): void
    {
        $data = $this->scenarioWithPublishedExam(
            availableFrom: now()->subHour(),
            deadlineAt: now()->addDay(),
        );

        $otherInstructor = $this->makeInstructor($data['department']);

        $this->actingAs($otherInstructor->user)
            ->postJson(route('monitoring.end', $data['examination']), [
                'end_policy' => ExamEndPolicy::AutoSubmit->value,
            ])
            ->assertForbidden();
    }

    public function test_instructor_can_extend_deadline(): void
    {
        $data = $this->scenarioWithPublishedExam(
            availableFrom: now()->subHour(),
            deadlineAt: now()->addHours(2),
        );

        $newDeadline = now()->addHours(4);

        $this->actingAs($data['instructorUser'])->postJson(
            route('monitoring.extend-deadline', $data['examination']),
            [
                'deadline_date' => $newDeadline->format('Y-m-d'),
                'deadline_time' => $newDeadline->format('H:i'),
                'reason' => 'Extended for technical issues.',
            ],
        )->assertOk();

        $data['examination']->refresh();
        $this->assertTrue($data['examination']->deadline_at->greaterThan(now()->addHours(3)));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'extend_deadline',
            'module' => 'examinations',
            'record_id' => $data['examination']->id,
        ]);
    }

    public function test_deadline_validation_rejects_invalid_schedule(): void
    {
        $data = $this->scenario();
        $start = now()->addDay();

        $this->actingAs($data['instructorUser'])->postJson(route('examinations.store'), [
            ...$this->examPayload($data),
            'availability_immediate' => false,
            'available_from_date' => $start->format('Y-m-d'),
            'available_from_time' => '10:00',
            'deadline_date' => $start->format('Y-m-d'),
            'deadline_time' => '09:00',
            'deadline_policy' => ExamDeadlinePolicy::StopAll->value,
            'status' => ExamStatus::Published->value,
        ])->assertStatus(422)->assertJsonValidationErrors(['deadline_date']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function scenario(): array
    {
        $year = AcademicYear::create(['name' => '2025-2026', 'is_active' => true, 'is_current' => true]);
        $semester = Semester::create(['academic_year_id' => $year->id, 'name' => 'First Semester', 'order' => 1, 'is_active' => true, 'is_current' => true]);
        $department = Department::create(['name' => 'IS Department', 'code' => 'IS', 'is_active' => true]);
        $program = Program::create(['department_id' => $department->id, 'name' => 'BSIS', 'code' => 'BSIS', 'is_active' => true]);
        $yearLevel = YearLevel::create(['program_id' => $program->id, 'name' => 'First Year', 'level' => 1, 'is_active' => true]);
        $section = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 1A',
            'code' => 'BSIS-1A',
            'is_active' => true,
        ]);
        $subject = Subject::create(['department_id' => $department->id, 'code' => 'IS101', 'name' => 'Intro to IS', 'units' => 3, 'is_active' => true]);
        $instructor = $this->makeInstructor($department);

        return compact('year', 'semester', 'department', 'program', 'yearLevel', 'section', 'subject', 'instructor') + [
            'instructorUser' => $instructor->user,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function scenarioWithPublishedExam($availableFrom, $deadlineAt): array
    {
        $data = $this->scenario();

        $studentUser = User::factory()->create(['is_active' => true]);
        $studentUser->assignRole(UserRole::Student->value);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2025-0001',
            'program_id' => $data['program']->id,
            'section_id' => $data['section']->id,
            'is_active' => true,
        ]);

        StudentSubject::create([
            'student_id' => $student->id,
            'subject_id' => $data['subject']->id,
            'academic_year_id' => $data['year']->id,
            'semester_id' => $data['semester']->id,
            'status' => StudentSubjectEnrollmentStatus::Verified,
            'verified_at' => now(),
        ]);

        $examination = Examination::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'EX-001',
            'title' => 'Schedule Test Exam',
            'subject_id' => $data['subject']->id,
            'instructor_id' => $data['instructor']->id,
            'academic_year_id' => $data['year']->id,
            'semester_id' => $data['semester']->id,
            'examination_period' => ExaminationPeriod::Midterm,
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'status' => ExamStatus::Active,
            'access_mode' => ExaminationAccessMode::SubjectOnly,
            'available_from' => $availableFrom,
            'deadline_at' => $deadlineAt,
            'deadline_policy' => ExamDeadlinePolicy::AllowActiveFinish,
        ]);

        ExaminationSetting::create(['examination_id' => $examination->id]);

        $version = ExaminationVersion::create([
            'examination_id' => $examination->id,
            'version_number' => 1,
        ]);

        return $data + compact('student', 'studentUser', 'examination', 'version');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function examPayload(array $data): array
    {
        return [
            'title' => 'Midterm Examination',
            'academic_year_id' => $data['year']->id,
            'semester_id' => $data['semester']->id,
            'subject_id' => $data['subject']->id,
            'program_id' => $data['program']->id,
            'year_level_id' => $data['yearLevel']->id,
            'section_ids' => [$data['section']->id],
            'access_mode' => ExaminationAccessMode::SubjectAndSections->value,
            'examination_period' => ExaminationPeriod::Midterm->value,
            'duration_minutes' => 60,
            'passing_percentage' => 75,
        ];
    }

    protected function makeInstructor(Department $department): Instructor
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        return Instructor::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employee_id' => 'EMP-'.Str::random(4),
            'is_active' => true,
        ]);
    }
}
