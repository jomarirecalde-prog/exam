<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExaminationAccessMode;
use App\Enums\ExaminationPeriod;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Examination;
use App\Models\ExaminationAttempt;
use App\Models\ExaminationVersion;
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

class ExaminationSectionAssignmentTest extends TestCase
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

    public function test_create_form_includes_target_sections_field(): void
    {
        $this->actingAs($this->admin())
            ->get(route('examinations.create'))
            ->assertOk()
            ->assertSee('Target Section(s)')
            ->assertSee('Student must be enrolled in the subject and belong to a selected section.');
    }

    public function test_admin_can_create_an_examination_for_one_section(): void
    {
        $admin = $this->admin();
        $structure = $this->academicStructure();

        $response = $this->actingAs($admin)->postJson(route('examinations.store'), $this->payload($structure, [
            'title' => 'Midterm Exam',
            'section_ids' => [$structure['sectionA']->id],
            'status' => ExamStatus::Published->value,
        ]));

        $response->assertOk();

        $exam = Examination::query()->where('title', 'Midterm Exam')->first();

        $this->assertNotNull($exam);
        $this->assertFalse($exam->needs_section_review);
        $this->assertEquals(ExamStatus::Published, $exam->status);
        $this->assertEquals([$structure['sectionA']->id], $exam->sections()->pluck('sections.id')->all());
    }

    public function test_admin_can_assign_multiple_sections_without_duplicates(): void
    {
        $structure = $this->academicStructure();

        $this->actingAs($this->admin())->postJson(route('examinations.store'), $this->payload($structure, [
            'title' => 'Shared Midterm',
            'section_ids' => [
                $structure['sectionA']->id,
                $structure['sectionB']->id,
                $structure['sectionA']->id,
            ],
        ]))->assertOk();

        $exam = Examination::query()->where('title', 'Shared Midterm')->first();

        $this->assertEqualsCanonicalizing(
            [$structure['sectionA']->id, $structure['sectionB']->id],
            $exam->sections()->pluck('sections.id')->all()
        );
        $this->assertSame(2, $exam->sections()->count());
    }

    public function test_section_selection_is_required(): void
    {
        $structure = $this->academicStructure();

        $this->actingAs($this->admin())->postJson(route('examinations.store'), $this->payload($structure, [
            'section_ids' => [],
        ]))->assertStatus(422)->assertJsonValidationErrors(['section_ids']);
    }

    public function test_incompatible_sections_are_rejected(): void
    {
        $structure = $this->academicStructure();

        $this->actingAs($this->admin())->postJson(route('examinations.store'), $this->payload($structure, [
            'year_level_id' => $structure['yearLevel1']->id,
            'section_ids' => [$structure['section2A']->id],
        ]))->assertStatus(422)->assertJsonValidationErrors(['section_ids']);
    }

    public function test_available_sections_are_filtered_by_program_and_year_level(): void
    {
        $structure = $this->academicStructure();

        $response = $this->actingAs($this->admin())->getJson(route('examinations.sections', [
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'subject_id' => $structure['subject']->id,
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel1']->id,
        ]));

        $response->assertOk();

        $ids = collect($response->json('sections'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing(
            [$structure['sectionA']->id, $structure['sectionB']->id, $structure['sectionC']->id],
            $ids
        );
        $this->assertNotContains($structure['section2A']->id, $ids);
        $this->assertNotContains($structure['itSection']->id, $ids);
        $this->assertNotContains($structure['hmSection']->id, $ids);
    }

    public function test_examination_list_shows_assigned_sections(): void
    {
        $structure = $this->academicStructure();
        $exam = $this->makeExam($structure, [$structure['sectionA']->id, $structure['sectionB']->id], [
            'title' => 'Midterm Exam',
            'status' => ExamStatus::Published,
        ]);

        $this->actingAs($this->admin())
            ->get(route('examinations.index'))
            ->assertOk()
            ->assertSee('Midterm Exam')
            ->assertSee($structure['sectionA']->name)
            ->assertSee($structure['sectionB']->name)
            ->assertSee($exam->periodLabel());
    }

    public function test_exams_without_sections_are_flagged_for_review(): void
    {
        $structure = $this->academicStructure();
        $this->makeExam($structure, [], [
            'title' => 'Unassigned Exam',
            'section_id' => null,
            'needs_section_review' => true,
            'status' => ExamStatus::Draft,
        ]);

        $this->actingAs($this->admin())
            ->get(route('examinations.index'))
            ->assertOk()
            ->assertSee('Unassigned Exam')
            ->assertSee('Needs section review');
    }

    public function test_edit_form_loads_previously_assigned_sections(): void
    {
        $structure = $this->academicStructure();
        $exam = $this->makeExam($structure, [$structure['sectionA']->id, $structure['sectionB']->id], [
            'title' => 'Prelim Exam',
        ]);

        $this->actingAs($this->admin())
            ->get(route('examinations.edit', $exam))
            ->assertOk()
            ->assertSee('Edit Examination')
            ->assertSee($structure['sectionA']->name)
            ->assertSee($structure['sectionB']->name);
    }

    public function test_update_can_add_and_remove_sections_when_no_attempts_exist(): void
    {
        $structure = $this->academicStructure();
        $exam = $this->makeExam($structure, [$structure['sectionA']->id], [
            'title' => 'Prelim Exam',
        ]);

        $this->actingAs($this->admin())->putJson(route('examinations.update', $exam), $this->payload($structure, [
            'title' => 'Prelim Exam',
            'section_ids' => [$structure['sectionB']->id, $structure['sectionC']->id],
        ]))->assertOk();

        $this->assertEqualsCanonicalizing(
            [$structure['sectionB']->id, $structure['sectionC']->id],
            $exam->sections()->pluck('sections.id')->all()
        );
    }

    public function test_section_with_existing_attempts_cannot_be_removed(): void
    {
        $structure = $this->academicStructure();
        $exam = $this->makeExam($structure, [$structure['sectionA']->id, $structure['sectionB']->id], [
            'title' => 'Protected Exam',
            'status' => ExamStatus::Published,
        ]);

        $student = $this->student($structure['sectionA']);
        $this->createAttempt($exam, $student);

        $this->actingAs($this->admin())->putJson(route('examinations.update', $exam), $this->payload($structure, [
            'title' => 'Protected Exam',
            'section_ids' => [$structure['sectionB']->id],
        ]))->assertStatus(422)->assertJsonValidationErrors(['section_ids']);

        $this->assertTrue($exam->sections()->where('sections.id', $structure['sectionA']->id)->exists());
        $this->assertSame(1, $exam->attempts()->count());
    }

    public function test_students_only_see_examinations_assigned_to_their_section(): void
    {
        $structure = $this->academicStructure();
        $this->makeExam($structure, [$structure['sectionA']->id, $structure['sectionB']->id], [
            'title' => 'Visible Midterm',
            'status' => ExamStatus::Published,
        ]);
        $this->makeExam($structure, [$structure['sectionC']->id], [
            'title' => 'Other Section Exam',
            'status' => ExamStatus::Published,
        ]);
        $this->makeExam($structure, [$structure['sectionA']->id], [
            'title' => 'Hidden Draft',
            'status' => ExamStatus::Draft,
        ]);

        $student = $this->student($structure['sectionA']);

        $this->actingAs($student->user)
            ->get(route('examinations.index'))
            ->assertOk()
            ->assertSee('Visible Midterm')
            ->assertDontSee('Other Section Exam')
            ->assertDontSee('Hidden Draft');
    }

    public function test_student_cannot_take_an_examination_assigned_to_another_section(): void
    {
        $structure = $this->academicStructure();
        $exam = $this->makeExam($structure, [$structure['sectionB']->id], [
            'title' => 'Section B Exam',
            'status' => ExamStatus::Published,
        ]);

        $student = $this->student($structure['sectionA']);

        $this->actingAs($student->user)
            ->get(route('examinations.take', $exam))
            ->assertForbidden();
    }

    public function test_student_can_take_an_examination_assigned_to_their_section(): void
    {
        $structure = $this->academicStructure();
        $exam = $this->makeExam($structure, [$structure['sectionA']->id, $structure['sectionB']->id], [
            'title' => 'Shared Exam',
            'status' => ExamStatus::Published,
        ]);

        $student = $this->student($structure['sectionA']);

        $this->actingAs($student->user)
            ->get(route('examinations.take', $exam))
            ->assertOk()
            ->assertSee('Shared Exam');
    }

    public function test_instructor_can_edit_own_examination(): void
    {
        $structure = $this->academicStructure();
        $instructor = $this->instructor($structure['department']);
        $instructor->user->assignRole(UserRole::Instructor->value);

        $exam = $this->makeExam($structure, [$structure['sectionA']->id], [
            'title' => 'Instructor Exam',
            'instructor_id' => $instructor->id,
        ]);

        $this->actingAs($instructor->user)
            ->get(route('examinations.edit', $exam))
            ->assertOk()
            ->assertSee('Edit Examination')
            ->assertSee('Instructor Exam');
    }

    public function test_instructor_cannot_edit_another_instructors_examination(): void
    {
        $structure = $this->academicStructure();
        $instructor = $this->instructor($structure['department']);
        $otherInstructor = $this->instructor($structure['department']);
        $instructor->user->assignRole(UserRole::Instructor->value);

        $exam = $this->makeExam($structure, [$structure['sectionA']->id], [
            'title' => 'Other Instructor Exam',
            'instructor_id' => $otherInstructor->id,
        ]);

        $this->actingAs($instructor->user)
            ->get(route('examinations.edit', $exam))
            ->assertForbidden();
    }

    public function test_instructor_examination_list_shows_only_own_examinations(): void
    {
        $structure = $this->academicStructure();
        $instructor = $this->instructor($structure['department']);
        $otherInstructor = $this->instructor($structure['department']);
        $instructor->user->assignRole(UserRole::Instructor->value);

        $ownExam = $this->makeExam($structure, [$structure['sectionA']->id], [
            'title' => 'My Midterm',
            'instructor_id' => $instructor->id,
        ]);

        $this->makeExam($structure, [$structure['sectionB']->id], [
            'title' => 'Someone Else Midterm',
            'instructor_id' => $otherInstructor->id,
        ]);

        $this->actingAs($instructor->user)
            ->get(route('examinations.index'))
            ->assertOk()
            ->assertSee('My Midterm')
            ->assertDontSee('Someone Else Midterm')
            ->assertSee(route('examinations.edit', $ownExam), false);
    }

    public function test_admin_can_edit_any_examination(): void
    {
        $structure = $this->academicStructure();
        $instructor = $this->instructor($structure['department']);

        $exam = $this->makeExam($structure, [$structure['sectionA']->id], [
            'title' => 'Admin Managed Exam',
            'instructor_id' => $instructor->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('examinations.index'))
            ->assertOk()
            ->assertSee('View')
            ->assertSee('Edit')
            ->assertSee(route('examinations.edit', $exam), false);

        $this->actingAs($this->admin())
            ->get(route('examinations.edit', $exam))
            ->assertOk()
            ->assertSee('Edit Examination');
    }

    public function test_students_cannot_open_the_create_form(): void
    {
        $structure = $this->academicStructure();
        $student = $this->student($structure['sectionA']);

        $this->actingAs($student->user)
            ->get(route('examinations.create'))
            ->assertForbidden();
    }

    public function test_instructor_available_sections_are_limited_to_assigned_sections(): void
    {
        $structure = $this->academicStructure();
        $instructor = $this->instructor($structure['department']);

        $instructor->user->assignRole(UserRole::Instructor->value);

        \Illuminate\Support\Facades\DB::table('subject_instructor')->insert([
            'subject_id' => $structure['subject']->id,
            'instructor_id' => $instructor->id,
            'section_id' => $structure['sectionA']->id,
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = collect($this->actingAs($instructor->user)->getJson(route('examinations.sections', [
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'subject_id' => $structure['subject']->id,
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel1']->id,
        ]))->json('sections'))->pluck('id')->all();

        $this->assertEquals([$structure['sectionA']->id], $ids);
    }

    protected function payload(array $structure, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Midterm Examination',
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'subject_id' => $structure['subject']->id,
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel1']->id,
            'section_ids' => [$structure['sectionA']->id],
            'access_mode' => ExaminationAccessMode::SubjectAndSections->value,
            'examination_period' => ExaminationPeriod::Midterm->value,
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'instructions' => 'Read each question carefully.',
            'randomize_questions' => true,
            'allow_back_navigation' => true,
            'auto_submit_on_expire' => true,
            'examination_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'status' => ExamStatus::Draft->value,
        ], $overrides);
    }

    protected function makeExam(array $structure, array $sectionIds, array $attributes = []): Examination
    {
        $exam = Examination::create(array_merge([
            'title' => 'Examination',
            'subject_id' => $structure['subject']->id,
            'section_id' => $sectionIds[0] ?? null,
            'instructor_id' => null,
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'examination_period' => ExaminationPeriod::Midterm,
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'status' => ExamStatus::Draft,
            'needs_section_review' => $sectionIds === [],
            'access_mode' => ExaminationAccessMode::SubjectAndSections,
        ], $attributes));

        if ($sectionIds !== []) {
            $exam->sections()->sync($sectionIds);
            $exam->forceFill([
                'section_id' => $sectionIds[0],
                'needs_section_review' => false,
            ])->save();
        }

        $exam->settings()->create(['max_attempts' => 1]);

        return $exam->refresh();
    }

    protected function createAttempt(Examination $examination, Student $student): ExaminationAttempt
    {
        $version = ExaminationVersion::create([
            'examination_id' => $examination->id,
            'version_number' => 1,
        ]);

        return ExaminationAttempt::create([
            'uuid' => (string) Str::uuid(),
            'examination_id' => $examination->id,
            'examination_version_id' => $version->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(UserRole::Admin->value);

        return $admin;
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

        $hmDepartment = Department::create([
            'code' => 'CHTM',
            'name' => 'College of Hospitality',
            'is_active' => true,
        ]);

        $program = Program::create([
            'department_id' => $department->id,
            'code' => 'BSIS',
            'name' => 'BS Information Systems',
            'is_active' => true,
        ]);

        $itProgram = Program::create([
            'department_id' => $department->id,
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
            'is_active' => true,
        ]);

        $hmProgram = Program::create([
            'department_id' => $hmDepartment->id,
            'code' => 'BSHM',
            'name' => 'BS Hospitality Management',
            'is_active' => true,
        ]);

        $yearLevel1 = YearLevel::create([
            'program_id' => $program->id,
            'name' => '1st Year',
            'level' => 1,
            'is_active' => true,
        ]);

        $yearLevel2 = YearLevel::create([
            'program_id' => $program->id,
            'name' => '2nd Year',
            'level' => 2,
            'is_active' => true,
        ]);

        $itYearLevel = YearLevel::create([
            'program_id' => $itProgram->id,
            'name' => '1st Year',
            'level' => 1,
            'is_active' => true,
        ]);

        $hmYearLevel = YearLevel::create([
            'program_id' => $hmProgram->id,
            'name' => '1st Year',
            'level' => 1,
            'is_active' => true,
        ]);

        $sectionA = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel1->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 1A',
            'code' => 'BSIS-1A',
            'is_active' => true,
        ]);

        $sectionB = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel1->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 1B',
            'code' => 'BSIS-1B',
            'is_active' => true,
        ]);

        $sectionC = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel1->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 1C',
            'code' => 'BSIS-1C',
            'is_active' => true,
        ]);

        $section2A = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel2->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 2A',
            'code' => 'BSIS-2A',
            'is_active' => true,
        ]);

        $itSection = Section::create([
            'program_id' => $itProgram->id,
            'year_level_id' => $itYearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIT 1A',
            'code' => 'BSIT-1A',
            'is_active' => true,
        ]);

        $hmSection = Section::create([
            'program_id' => $hmProgram->id,
            'year_level_id' => $hmYearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSHM 1A',
            'code' => 'BSHM-1A',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'department_id' => $department->id,
            'code' => 'IS101',
            'name' => 'Introduction to Information Systems',
            'units' => 3,
            'is_active' => true,
        ]);

        return compact(
            'year',
            'semester',
            'department',
            'program',
            'yearLevel1',
            'yearLevel2',
            'sectionA',
            'sectionB',
            'sectionC',
            'section2A',
            'itSection',
            'hmSection',
            'subject',
        );
    }
}
