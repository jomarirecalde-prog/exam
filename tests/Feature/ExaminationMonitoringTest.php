<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExaminationAccessMode;
use App\Enums\ExaminationPeriod;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
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

class ExaminationMonitoringTest extends TestCase
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

    public function test_instructor_can_view_monitoring_dashboard_page(): void
    {
        $data = $this->monitoringScenario();

        $this->actingAs($data['instructorUser'])
            ->get(route('monitoring.index'))
            ->assertOk()
            ->assertSee('Examination Monitoring')
            ->assertSee('Policy Monitoring Exam');
    }

    public function test_student_cannot_access_monitoring_page(): void
    {
        $data = $this->monitoringScenario();

        $this->actingAs($data['studentUser'])
            ->get(route('monitoring.index'))
            ->assertForbidden();
    }

    public function test_unauthorized_instructor_cannot_access_monitoring_data(): void
    {
        $data = $this->monitoringScenario();
        $otherInstructor = $this->instructor($data['structure']['department']);

        $this->actingAs($otherInstructor->user)
            ->getJson(route('monitoring.data', $data['examination']))
            ->assertForbidden();
    }

    public function test_monitoring_data_includes_eligible_not_started_students(): void
    {
        $data = $this->monitoringScenario();

        $response = $this->actingAs($data['instructorUser'])
            ->getJson(route('monitoring.data', $data['examination']))
            ->assertOk();

        $response->assertJsonPath('summary.total', 1);
        $response->assertJsonPath('summary.not_started', 1);
        $response->assertJsonPath('students.0.monitoring_status', 'NOT_STARTED');
        $response->assertJsonPath('students.0.progress_percent', 0);
    }

    public function test_progress_updates_when_student_answers_questions(): void
    {
        $data = $this->monitoringScenario(withQuestions: true);
        $this->startAttempt($data);

        $question = $data['questions'][0];

        $this->actingAs($data['studentUser'])
            ->postJson(route('examinations.attempts.answers.store', [
                'examination' => $data['examination'],
                'question' => $question->id,
            ]), [
                'answer' => 'A',
            ])
            ->assertOk();

        $this->actingAs($data['studentUser'])
            ->postJson(route('examinations.attempts.progress', $data['examination']), [
                'current_question_index' => 1,
                'connection_status' => 'online',
            ])
            ->assertOk();

        $response = $this->actingAs($data['instructorUser'])
            ->getJson(route('monitoring.data', $data['examination']))
            ->assertOk();

        $response->assertJsonPath('summary.taking_exam', 1);
        $response->assertJsonPath('students.0.progress_percent', 50);
        $response->assertJsonPath('students.0.answered_count', 1);
        $response->assertJsonPath('students.0.current_question', 1);
        $response->assertJsonPath('students.0.monitoring_status', 'TAKING_EXAM');
    }

    public function test_monitoring_reflects_locked_attempt_after_max_warnings(): void
    {
        $data = $this->monitoringScenario(withQuestions: true);
        $attempt = $this->startAttempt($data);

        for ($i = 1; $i <= 3; $i++) {
            $this->travel(4)->seconds();

            $this->actingAs($data['studentUser'])
                ->postJson(route('examinations.attempts.violations.store', $data['examination']), [
                    'violation_type' => 'TAB_OR_WINDOW_SWITCH',
                    'client_event_id' => "evt-{$i}",
                ])
                ->assertOk();
        }

        $response = $this->actingAs($data['instructorUser'])
            ->getJson(route('monitoring.data', $data['examination']))
            ->assertOk();

        $response->assertJsonPath('summary.locked', 1);
        $response->assertJsonPath('students.0.monitoring_status', 'LOCKED');
        $response->assertJsonPath('students.0.can_reactivate', true);
        $this->assertSame(AttemptStatus::LockedViolationLimit, $attempt->fresh()->status);
    }

    public function test_reactivation_marks_offline_student_as_pending(): void
    {
        $data = $this->monitoringScenario(withQuestions: true);
        $attempt = $this->startAttempt($data);
        $attempt->update([
            'status' => AttemptStatus::LockedViolationLimit,
            'locked_at' => now(),
            'lock_reason' => 'Maximum violation warnings reached (3/3)',
            'warning_count' => 3,
            'connection_status' => 'offline',
            'offline_enabled' => true,
            'last_activity_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($data['instructorUser'])
            ->postJson(route('monitoring.reactivate', $attempt), [
                'reactivation_reason' => 'Allowed to continue after verification.',
                'warning_mode' => 'reset',
            ])
            ->assertOk()
            ->assertJsonPath('attempt.reactivation_pending', true);

        $response = $this->actingAs($data['instructorUser'])
            ->getJson(route('monitoring.data', $data['examination']))
            ->assertOk();

        $response->assertJsonPath('students.0.reactivation_pending', true);
        $response->assertJsonPath('students.0.monitoring_status', 'REACTIVATED');
    }

    public function test_monitoring_supports_delta_polling_with_since_parameter(): void
    {
        $data = $this->monitoringScenario(withQuestions: true);
        $this->startAttempt($data);

        $initial = $this->actingAs($data['instructorUser'])
            ->getJson(route('monitoring.data', $data['examination']))
            ->assertOk()
            ->json();

        $since = $initial['server_time'];

        $this->travel(2)->seconds();

        $this->actingAs($data['studentUser'])
            ->postJson(route('examinations.attempts.progress', $data['examination']), [
                'current_question_index' => 2,
                'connection_status' => 'online',
            ])
            ->assertOk();

        $delta = $this->actingAs($data['instructorUser'])
            ->getJson(route('monitoring.data', [
                'examination' => $data['examination'],
                'since' => $since,
            ]))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($delta['students']);
        $this->assertSame(2, $delta['students'][0]['current_question']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function monitoringScenario(bool $withQuestions = false): array
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
            'title' => 'Policy Monitoring Exam',
            'duration_minutes' => 60,
            'total_items' => 2,
            'status' => ExamStatus::Active,
            'access_mode' => ExaminationAccessMode::SubjectAndSections,
            'needs_section_review' => false,
            'current_version' => 1,
        ]);

        $examination->sections()->attach($structure['sectionA']->id);

        ExaminationVersion::create([
            'examination_id' => $examination->id,
            'version_number' => 1,
        ]);

        $questions = collect();

        if ($withQuestions) {
            foreach (['First question?', 'Second question?'] as $index => $text) {
                $question = Question::create([
                    'uuid' => (string) Str::uuid(),
                    'subject_id' => $structure['subject']->id,
                    'question_text' => $text,
                    'type' => QuestionType::MultipleChoice,
                    'points' => 1,
                    'created_by' => $instructor->user->id,
                ]);

                QuestionChoice::create([
                    'question_id' => $question->id,
                    'label' => 'A',
                    'choice_text' => 'Option A',
                    'is_correct' => true,
                ]);

                $examination->examQuestions()->create([
                    'question_id' => $question->id,
                    'examination_version_id' => ExaminationVersion::first()->id,
                    'order' => $index + 1,
                ]);

                $questions->push($question);
            }
        }

        return [
            'structure' => $structure,
            'instructor' => $instructor,
            'instructorUser' => $instructor->user,
            'student' => $student,
            'studentUser' => $studentUser,
            'examination' => $examination,
            'questions' => $questions,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function startAttempt(array $data): ExaminationAttempt
    {
        $this->actingAs($data['studentUser'])
            ->postJson(route('examinations.attempts.accept-policy', $data['examination']))
            ->assertOk();

        $this->actingAs($data['studentUser'])
            ->postJson(route('examinations.attempts.start', $data['examination']))
            ->assertOk();

        return ExaminationAttempt::query()
            ->where('examination_id', $data['examination']->id)
            ->where('student_id', $data['student']->id)
            ->firstOrFail();
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

    /**
     * @return array<string, mixed>
     */
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
