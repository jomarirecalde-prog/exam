<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExaminationAccessMode;
use App\Enums\ExaminationPeriod;
use App\Enums\ExamStatus;
use App\Enums\OfflineExaminationMode;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExaminationSetting;
use App\Models\ExaminationVersion;
use App\Models\Program;
use App\Models\Question;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\Examinations\ExamAttemptSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OfflineExamSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate(UserRole::Student->value);
    }

    public function test_duplicate_sync_events_are_idempotent(): void
    {
        $department = Department::create(['code' => 'CS', 'name' => 'CS', 'is_active' => true]);
        $program = Program::create(['department_id' => $department->id, 'code' => 'BSCS', 'name' => 'BSCS', 'is_active' => true]);
        $yearLevel = YearLevel::create(['program_id' => $program->id, 'name' => 'Year 1', 'level' => 1, 'is_active' => true]);
        $year = AcademicYear::create(['name' => '2025-2026', 'is_active' => true, 'is_current' => true]);
        $semester = Semester::create(['academic_year_id' => $year->id, 'name' => 'First', 'order' => 1, 'is_active' => true, 'is_current' => true]);
        $subject = Subject::create(['code' => 'CS101', 'name' => 'Intro', 'is_active' => true]);
        $section = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'code' => 'A',
            'name' => 'Section A',
            'is_active' => true,
        ]);

        $studentUser = User::factory()->create();
        $studentUser->assignRole('student');
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id' => '2026-0001',
            'section_id' => $section->id,
        ]);

        $examination = Examination::create([
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'examination_period' => ExaminationPeriod::Midterm,
            'title' => 'Offline Sync Test',
            'duration_minutes' => 60,
            'status' => ExamStatus::Published,
            'access_mode' => ExaminationAccessMode::SubjectAndSections,
            'needs_section_review' => false,
            'current_version' => 1,
        ]);
        $examination->sections()->attach($section->id);

        ExaminationSetting::create([
            'examination_id' => $examination->id,
            'offline_examination_mode' => OfflineExaminationMode::Allowed,
            'allow_offline_continuation' => true,
            'allow_pending_offline_submission' => true,
        ]);

        ExaminationVersion::create([
            'examination_id' => $examination->id,
            'version_number' => 1,
        ]);
        $version = ExaminationVersion::first();

        $question = Question::create([
            'uuid' => (string) Str::uuid(),
            'subject_id' => $subject->id,
            'question_text' => 'Sample?',
            'type' => QuestionType::MultipleChoice,
            'points' => 1,
        ]);

        $attempt = ExaminationAttempt::create([
            'uuid' => (string) Str::uuid(),
            'examination_id' => $examination->id,
            'examination_version_id' => $version->id,
            'student_id' => $student->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
            'expires_at' => now()->addHour(),
            'offline_enabled' => true,
            'authorized_device_id' => 'device-test-1',
        ]);

        $sync = app(ExamAttemptSyncService::class);
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $event = [[
            'client_event_uuid' => $uuid,
            'event_type' => 'answer_updated',
            'payload' => [
                'question_id' => $question->id,
                'answer' => 'B',
                'is_flagged' => false,
                'client_revision' => '1000',
            ],
        ]];

        $first = $sync->syncEvents($attempt, $event, 'device-test-1');
        $second = $sync->syncEvents($attempt->fresh(), $event, 'device-test-1');

        $this->assertSame(1, $first['processed']);
        $this->assertSame(1, $second['duplicates']);
        $this->assertDatabaseCount('exam_sync_events', 1);
        $this->assertDatabaseCount('student_answers', 1);
    }
}
