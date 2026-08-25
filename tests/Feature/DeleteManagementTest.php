<?php

namespace Tests\Feature;

use App\Enums\StudentRegistrationStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeleteManagementTest extends TestCase
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

    public function test_admin_can_soft_delete_student(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin())
            ->delete(route('students.destroy', $student))
            ->assertRedirect(route('students.index'));

        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->assertFalse($student->user->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'delete',
            'module' => 'students',
            'record_id' => $student->id,
        ]);
    }

    public function test_superadmin_can_view_and_restore_deleted_students(): void
    {
        $student = $this->student();
        $superadmin = $this->superadmin();

        $this->actingAs($this->admin())
            ->delete(route('students.destroy', $student));

        $this->actingAs($superadmin)
            ->get(route('students.deleted.index'))
            ->assertOk()
            ->assertSee($student->student_id);

        $this->actingAs($superadmin)
            ->post(route('students.restore', $student->id))
            ->assertRedirect(route('students.deleted.index'));

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'deleted_at' => null,
            'is_active' => true,
        ]);
        $this->assertTrue($student->user->fresh()->is_active);
    }

    public function test_admin_cannot_access_deleted_students_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('students.deleted.index'))
            ->assertForbidden();
    }

    public function test_instructor_cannot_delete_academic_records(): void
    {
        $student = $this->student();
        $structure = [
            'department' => $student->program->department,
            'program' => $student->program,
            'section' => $student->section,
        ];

        $instructor = User::factory()->create(['is_active' => true]);
        $instructor->assignRole(UserRole::Instructor->value);

        $this->actingAs($instructor)
            ->delete(route('students.destroy', $student))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->delete(route('departments.destroy', $structure['department']))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->delete(route('programs.destroy', $structure['program']))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->delete(route('sections.destroy', $structure['section']))
            ->assertForbidden();
    }

    public function test_admin_can_delete_subject_without_dependencies(): void
    {
        $subject = Subject::create([
            'department_id' => $this->academicStructure()['department']->id,
            'code' => 'TEMP101',
            'name' => 'Temporary Subject',
            'units' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('subjects.destroy', $subject))
            ->assertRedirect(route('subjects.index'));

        $this->assertFalse($subject->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'delete',
            'module' => 'subjects',
            'record_id' => $subject->id,
        ]);
    }

    public function test_subject_delete_is_blocked_when_students_are_enrolled(): void
    {
        $student = $this->student();
        $subject = Subject::create([
            'department_id' => $student->program->department_id,
            'code' => 'IS101',
            'name' => 'Intro to IS',
            'units' => 3,
            'is_active' => true,
        ]);

        $year = AcademicYear::query()->firstOrFail();
        $semester = Semester::query()->firstOrFail();

        \App\Models\StudentSubject::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'status' => 'verified',
        ]);

        $this->actingAs($this->admin())
            ->from(route('subjects.index'))
            ->delete(route('subjects.destroy', $subject))
            ->assertRedirect(route('subjects.index'))
            ->assertSessionHas('error');

        $this->assertTrue($subject->fresh()->is_active);
    }

    public function test_instructor_cannot_delete_subjects(): void
    {
        $subject = Subject::create([
            'department_id' => $this->academicStructure()['department']->id,
            'code' => 'TEMP102',
            'name' => 'Another Temporary Subject',
            'units' => 3,
            'is_active' => true,
        ]);

        $instructor = User::factory()->create(['is_active' => true]);
        $instructor->assignRole(UserRole::Instructor->value);

        $this->actingAs($instructor)
            ->delete(route('subjects.destroy', $subject))
            ->assertForbidden();
    }

    public function test_department_delete_is_blocked_when_programs_exist(): void
    {
        $structure = $this->academicStructure();

        $this->actingAs($this->admin())
            ->from(route('departments.index'))
            ->delete(route('departments.destroy', $structure['department']))
            ->assertRedirect(route('departments.index'))
            ->assertSessionHas('error');

        $this->assertTrue($structure['department']->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'delete_blocked',
            'module' => 'departments',
            'record_id' => $structure['department']->id,
        ]);
    }

    public function test_department_can_be_deleted_when_no_dependencies_exist(): void
    {
        $department = Department::create([
            'code' => 'TEMP',
            'name' => 'Temporary Department',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('departments.destroy', $department))
            ->assertRedirect(route('departments.index'));

        $this->assertFalse($department->fresh()->is_active);
    }

    public function test_program_delete_is_blocked_when_students_exist(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin())
            ->from(route('programs.index'))
            ->delete(route('programs.destroy', $student->program))
            ->assertRedirect(route('programs.index'))
            ->assertSessionHas('error');

        $this->assertTrue($student->program->fresh()->is_active);
    }

    public function test_section_delete_is_blocked_when_students_are_assigned(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin())
            ->from(route('sections.index'))
            ->delete(route('sections.destroy', $student->section))
            ->assertRedirect(route('sections.index'))
            ->assertSessionHas('error');

        $this->assertTrue($student->section->fresh()->is_active);
    }

    public function test_deleted_student_cannot_log_in(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin())
            ->delete(route('students.destroy', $student));

        $this->assertFalse($student->user->fresh()->is_active);
        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(UserRole::Admin->value);

        return $admin;
    }

    protected function superadmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Superadmin->value);

        return $user;
    }

    protected function student(): Student
    {
        $structure = $this->academicStructure();

        $user = User::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'name' => 'Juan Dela Cruz',
            'email' => 'juan.delacruz@exam.local',
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::Student->value);

        return Student::create([
            'user_id' => $user->id,
            'student_id' => '2026-00001',
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel']->id,
            'section_id' => $structure['section']->id,
            'registration_status' => StudentRegistrationStatus::Approved,
            'is_active' => true,
        ])->load(['user', 'program', 'section']);
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
            'name' => '1st Semester',
            'order' => 1,
            'is_current' => true,
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
            'name' => '2nd Year',
            'level' => 2,
            'is_active' => true,
        ]);

        $section = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 2A',
            'code' => 'BSIS-2A',
            'is_active' => true,
        ]);

        return compact('year', 'semester', 'department', 'program', 'yearLevel', 'section');
    }
}
