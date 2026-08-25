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
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Superadmin->value);
        Role::findOrCreate(UserRole::Admin->value);
        Role::findOrCreate(UserRole::Student->value);
    }

    public function test_admin_can_view_students_index(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin())
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Maria Santos')
            ->assertSee($student->student_id)
            ->assertSee('View')
            ->assertSee('Edit');
    }

    public function test_superadmin_can_view_and_edit_student_pages(): void
    {
        $student = $this->student();
        $superadmin = User::factory()->create(['is_active' => true]);
        $superadmin->assignRole(UserRole::Superadmin->value);

        $this->actingAs($superadmin)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Maria Santos');

        $this->actingAs($superadmin)
            ->get(route('students.edit', $student))
            ->assertOk()
            ->assertSee('Save Changes');
    }

    public function test_admin_can_update_a_student(): void
    {
        $admin = $this->admin();
        $student = $this->student();

        $this->actingAs($admin)
            ->put(route('students.update', $student), [
                'first_name' => 'Maria',
                'middle_name' => 'L',
                'last_name' => 'Santos-Reyes',
                'suffix' => '',
                'sex' => 'female',
                'date_of_birth' => '2005-03-10',
                'phone' => '09170000000',
                'email' => 'maria.updated@exam.local',
                'home_address' => 'Manila',
                'student_id' => $student->student_id,
                'department_id' => $student->program->department_id,
                'program_id' => $student->program_id,
                'year_level_id' => $student->year_level_id,
                'section_id' => $student->section_id,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => '1',
            ])
            ->assertRedirect(route('students.show', $student));

        $student->refresh();

        $this->assertSame('Santos-Reyes', $student->user->last_name);
        $this->assertSame('maria.updated@exam.local', $student->user->email);
    }

    public function test_non_admin_cannot_manage_students(): void
    {
        $student = $this->student();
        $user = User::factory()->create();
        $user->assignRole(UserRole::Student->value);

        $this->actingAs($user)
            ->get(route('students.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('students.show', $student))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('students.edit', $student))
            ->assertForbidden();
    }

    protected function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(UserRole::Admin->value);

        return $admin;
    }

    protected function student(): Student
    {
        $structure = $this->academicStructure();

        $user = User::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'name' => 'Maria Santos',
            'email' => 'maria.santos@exam.local',
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::Student->value);

        return Student::create([
            'user_id' => $user->id,
            'student_id' => '2026-0001',
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel']->id,
            'section_id' => $structure['section']->id,
            'registration_status' => StudentRegistrationStatus::Approved,
            'is_active' => true,
        ])->load(['user', 'program']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function academicStructure(): array
    {
        $year = AcademicYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-06-01',
            'end_date' => '2027-03-31',
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
            'name' => 'College of Computing and Information Sciences',
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
