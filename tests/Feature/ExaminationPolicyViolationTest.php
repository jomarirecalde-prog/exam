<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExaminationAccessMode;
use App\Enums\ExaminationPeriod;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Enums\ViolationType;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExaminationVersion;
use App\Models\ExamPolicyAcceptance;
use App\Models\ExamViolation;
use App\Models\Instructor;
use App\Models\Program;
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

class ExaminationPolicyViolationTest extends TestCase
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

    public function test_student_must_accept_policy_before_starting(): void
    {
        ['studentUser' => $studentUser, 'examination' => $examination] = $this->scenario();

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.start', $examination))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'You must accept the examination policy before starting.']);
    }

    public function test_policy_acceptance_is_recorded(): void
    {
        ['studentUser' => $studentUser, 'examination' => $examination, 'student' => $student] = $this->scenario();

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.accept-policy', $examination))
            ->assertOk()
            ->assertJsonPath('attempt.policy_accepted', true);

        $attempt = ExaminationAttempt::query()->first();

        $this->assertNotNull($attempt);
        $this->assertNotNull($attempt->policy_accepted_at);
        $this->assertSame(config('examination.policy_version'), $attempt->policy_version);

        $this->assertDatabaseHas('exam_policy_acceptances', [
            'student_id' => $student->id,
            'examination_id' => $examination->id,
            'examination_attempt_id' => $attempt->id,
        ]);
    }

    public function test_student_can_start_after_accepting_policy(): void
    {
        ['studentUser' => $studentUser, 'examination' => $examination] = $this->scenario();

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.accept-policy', $examination))
            ->assertOk();

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.start', $examination))
            ->assertOk()
            ->assertJsonPath('attempt.status', AttemptStatus::InProgress->value);
    }

    public function test_violation_increments_warning_count_on_server(): void
    {
        ['studentUser' => $studentUser, 'examination' => $examination, 'attempt' => $attempt] = $this->startedAttempt();

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.violations.store', $examination), [
                'violation_type' => ViolationType::TabOrWindowSwitch->value,
                'client_event_id' => 'evt-1',
            ])
            ->assertOk()
            ->assertJsonPath('warning_count', 1)
            ->assertJsonPath('locked', false);

        $this->assertDatabaseHas('exam_violations', [
            'examination_attempt_id' => $attempt->id,
            'violation_type' => ViolationType::TabOrWindowSwitch->value,
            'warning_number' => 1,
        ]);
    }

    public function test_duplicate_violations_are_deduped(): void
    {
        ['studentUser' => $studentUser, 'examination' => $examination, 'attempt' => $attempt] = $this->startedAttempt();

        $payload = [
            'violation_type' => ViolationType::TabOrWindowSwitch->value,
            'client_event_id' => 'evt-dup',
        ];

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.violations.store', $examination), $payload)
            ->assertOk()
            ->assertJsonPath('recorded', true);

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.violations.store', $examination), $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, ExamViolation::query()->where('examination_attempt_id', $attempt->id)->count());
        $this->assertSame(1, $attempt->fresh()->warning_count);
    }

    public function test_third_violation_locks_examination_and_preserves_answers(): void
    {
        ['studentUser' => $studentUser, 'examination' => $examination, 'attempt' => $attempt, 'question' => $question] = $this->startedAttempt();

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.answers.bulk', $examination), [
                'answers' => [
                    ['question_id' => $question->id, 'answer' => 'B'],
                ],
            ])
            ->assertOk();

        foreach (['evt-a', 'evt-b', 'evt-c'] as $eventId) {
            $this->actingAs($studentUser)
                ->postJson(route('examinations.attempts.violations.store', $examination), [
                    'violation_type' => ViolationType::CopyAttempt->value,
                    'client_event_id' => $eventId,
                ])
                ->assertOk();
        }

        $attempt->refresh();

        $this->assertSame(AttemptStatus::LockedViolationLimit, $attempt->status);
        $this->assertSame(3, $attempt->warning_count);
        $this->assertNotNull($attempt->locked_at);
        $this->assertDatabaseHas('student_answers', [
            'examination_attempt_id' => $attempt->id,
            'question_id' => $question->id,
        ]);
    }

    public function test_locked_attempt_cannot_save_answers(): void
    {
        ['studentUser' => $studentUser, 'examination' => $examination, 'attempt' => $attempt, 'question' => $question] = $this->startedAttempt();

        $attempt->update([
            'status' => AttemptStatus::LockedViolationLimit,
            'warning_count' => 3,
            'locked_at' => now(),
        ]);

        $this->actingAs($studentUser)
            ->postJson(route('examinations.attempts.answers.bulk', $examination), [
                'answers' => [
                    ['question_id' => $question->id, 'answer' => 'A'],
                ],
            ])
            ->assertStatus(423);
    }

    public function test_unauthorized_instructor_cannot_reactivate(): void
    {
        ['examination' => $examination, 'attempt' => $attempt] = $this->lockedAttempt();
        $otherInstructor = $this->instructorUser();

        $this->actingAs($otherInstructor)
            ->postJson(route('monitoring.reactivate', $attempt), [
                'reactivation_reason' => 'Should not work',
                'warning_mode' => 'reset',
            ])
            ->assertForbidden();
    }

    public function test_authorized_instructor_can_reactivate_and_reset_warnings(): void
    {
        ['examination' => $examination, 'attempt' => $attempt, 'instructorUser' => $instructorUser, 'studentUser' => $studentUser] = $this->lockedAttempt();

        $this->actingAs($instructorUser)
            ->postJson(route('monitoring.reactivate', $attempt), [
                'reactivation_reason' => 'False detection due to technical issue',
                'warning_mode' => 'reset',
            ])
            ->assertOk()
            ->assertJsonPath('attempt.status', AttemptStatus::InProgress->value)
            ->assertJsonPath('attempt.warning_count', 0);

        $attempt->refresh();
        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame(0, $attempt->warning_count);
        $this->assertSame(1, $attempt->reactivation_count);

        $this->actingAs($studentUser)
            ->get(route('examinations.take', $examination))
            ->assertOk();
    }

    public function test_monitoring_dashboard_returns_attempt_rows(): void
    {
        ['examination' => $examination, 'instructorUser' => $instructorUser] = $this->lockedAttempt();

        $this->actingAs($instructorUser)
            ->getJson(route('monitoring.data', $examination))
            ->assertOk()
            ->assertJsonStructure(['attempts' => [['student_name', 'warning_count', 'status']]]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function scenario(): array
    {
        $structure = $this->academicStructure();
        $instructor = $this->instructor($structure['department']);
        $student = $this->student($structure['sectionA']);
        $studentUser = $student->user;

        $examination = Examination::create([
            'subject_id' => $structure['subject']->id,
            'instructor_id' => $instructor->id,
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'examination_period' => ExaminationPeriod::Midterm,
            'title' => 'Policy Test Exam',
            'duration_minutes' => 60,
            'status' => ExamStatus::Published,
            'access_mode' => ExaminationAccessMode::SubjectAndSections,
            'needs_section_review' => false,
            'current_version' => 1,
        ]);

        $examination->sections()->attach($structure['sectionA']->id);

        ExaminationVersion::create([
            'examination_id' => $examination->id,
            'version_number' => 1,
        ]);

        return [
            'structure' => $structure,
            'instructor' => $instructor,
            'instructorUser' => $instructor->user,
            'student' => $student,
            'studentUser' => $studentUser,
            'examination' => $examination,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function startedAttempt(): array
    {
        $data = $this->scenario();

        $question = \App\Models\Question::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $data['structure']['subject']->id,
            'question_text' => 'Sample question?',
            'type' => \App\Enums\QuestionType::MultipleChoice,
            'points' => 1,
            'created_by' => $data['instructorUser']->id,
        ]);

        \App\Models\QuestionChoice::create([
            'question_id' => $question->id,
            'label' => 'A',
            'choice_text' => 'One',
            'is_correct' => false,
        ]);

        \App\Models\QuestionChoice::create([
            'question_id' => $question->id,
            'label' => 'B',
            'choice_text' => 'Two',
            'is_correct' => true,
        ]);

        $data['examination']->examQuestions()->create([
            'question_id' => $question->id,
            'examination_version_id' => ExaminationVersion::first()->id,
            'order' => 1,
        ]);

        $this->actingAs($data['studentUser'])
            ->postJson(route('examinations.attempts.accept-policy', $data['examination']))
            ->assertOk();

        $this->actingAs($data['studentUser'])
            ->postJson(route('examinations.attempts.start', $data['examination']))
            ->assertOk();

        $attempt = ExaminationAttempt::query()->firstOrFail();

        return array_merge($data, [
            'attempt' => $attempt,
            'question' => $question,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function lockedAttempt(): array
    {
        $data = $this->startedAttempt();

        $data['attempt']->update([
            'status' => AttemptStatus::LockedViolationLimit,
            'warning_count' => 3,
            'locked_at' => now(),
            'lock_reason' => 'Maximum violation warnings reached (3/3)',
        ]);

        ExamViolation::create([
            'examination_attempt_id' => $data['attempt']->id,
            'student_id' => $data['student']->id,
            'examination_id' => $data['examination']->id,
            'violation_type' => ViolationType::TabOrWindowSwitch,
            'warning_number' => 3,
            'detected_at' => now(),
        ]);

        return $data;
    }

    protected function instructorUser(): User
    {
        $department = Department::create([
            'code' => 'OTHER',
            'name' => 'Other Department',
            'is_active' => true,
        ]);

        return $this->instructor($department)->user;
    }

    protected function instructor(Department $department): Instructor
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        return Instructor::create([
            'user_id' => $user->id,
            'employee_id' => 'EMP-'.Str::upper(Str::random(5)),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
    }

    protected function student(Section $section): Student
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Student->value);

        return Student::create([
            'user_id' => $user->id,
            'student_id' => 'STU-'.Str::upper(Str::random(6)),
            'program_id' => $section->program_id,
            'year_level_id' => $section->year_level_id,
            'section_id' => $section->id,
            'is_active' => true,
        ]);
    }

    protected function academicStructure(): array
    {
        $year = AcademicYear::create([
            'name' => '2026-2027',
            'is_current' => true,
            'is_active' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'First Semester',
            'order' => 1,
            'is_current' => true,
            'is_active' => true,
        ]);

        $department = Department::create([
            'code' => 'CCIS',
            'name' => 'College of Computing',
            'is_active' => true,
        ]);

        $program = Program::create([
            'department_id' => $department->id,
            'code' => 'BSIS',
            'name' => 'BS Information Systems',
            'is_active' => true,
        ]);

        $yearLevel = YearLevel::create([
            'program_id' => $program->id,
            'name' => '1st Year',
            'level' => 1,
            'is_active' => true,
        ]);

        $sectionA = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 1A',
            'code' => 'BSIS-1A',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'department_id' => $department->id,
            'code' => 'IS101',
            'name' => 'Introduction to Information Systems',
            'units' => 3,
            'is_active' => true,
        ]);

        return compact('year', 'semester', 'department', 'program', 'yearLevel', 'sectionA', 'subject');
    }
}
