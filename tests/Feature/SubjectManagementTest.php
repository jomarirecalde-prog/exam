<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Admin->value);
        Role::findOrCreate(UserRole::Student->value);
        Role::findOrCreate(UserRole::Instructor->value);
    }

    public function test_admin_can_view_subjects_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('subjects.index'))
            ->assertOk()
            ->assertSee('Create Subject');
    }

    public function test_admin_can_create_a_subject(): void
    {
        $department = $this->department();

        $response = $this->actingAs($this->admin())->post(route('subjects.store'), [
            'code' => 'is101',
            'name' => 'Introduction to Information Systems',
            'description' => 'Foundations of IS',
            'department_id' => $department->id,
            'units' => '3',
            'is_active' => '1',
        ]);

        $subject = Subject::query()->where('code', 'IS101')->first();

        $this->assertNotNull($subject);
        $response->assertRedirect(route('subjects.show', $subject));
    }

    public function test_admin_can_create_a_subject_with_an_instructor_assignment(): void
    {
        $department = $this->department();
        $term = $this->term();
        $instructor = $this->instructor($department);

        $response = $this->actingAs($this->admin())->post(route('subjects.store'), [
            'code' => 'CS201',
            'name' => 'Data Structures',
            'department_id' => $department->id,
            'units' => '3',
            'is_active' => '1',
            'instructor_id' => $instructor->id,
            'academic_year_id' => $term['year']->id,
            'semester_id' => $term['semester']->id,
        ]);

        $subject = Subject::query()->where('code', 'CS201')->first();

        $this->assertNotNull($subject);
        $response->assertRedirect(route('subjects.show', $subject));

        $this->assertDatabaseHas('subject_instructor', [
            'subject_id' => $subject->id,
            'instructor_id' => $instructor->id,
            'section_id' => null,
            'academic_year_id' => $term['year']->id,
            'semester_id' => $term['semester']->id,
        ]);
    }

    public function test_instructor_assignment_requires_term_fields(): void
    {
        $department = $this->department();
        $instructor = $this->instructor($department);

        $this->actingAs($this->admin())
            ->post(route('subjects.store'), [
                'code' => 'CS202',
                'name' => 'Algorithms',
                'department_id' => $department->id,
                'units' => '3',
                'is_active' => '1',
                'instructor_id' => $instructor->id,
            ])
            ->assertSessionHasErrors(['academic_year_id', 'semester_id']);
    }

    public function test_instructor_must_belong_to_selected_department(): void
    {
        $department = $this->department();
        $otherDepartment = Department::create([
            'code' => 'COED',
            'name' => 'College of Education',
            'is_active' => true,
        ]);
        $term = $this->term();
        $instructor = $this->instructor($otherDepartment);

        $this->actingAs($this->admin())
            ->post(route('subjects.store'), [
                'code' => 'ED101',
                'name' => 'Foundations of Teaching',
                'department_id' => $department->id,
                'units' => '3',
                'is_active' => '1',
                'instructor_id' => $instructor->id,
                'academic_year_id' => $term['year']->id,
                'semester_id' => $term['semester']->id,
            ])
            ->assertSessionHasErrors('instructor_id');
    }

    public function test_admin_can_create_subject_with_same_code_and_name_for_different_instructor(): void
    {
        $department = $this->department();
        $term = $this->term();
        $firstInstructor = $this->instructor($department, 'EMP-101');
        $secondInstructor = $this->instructor($department, 'EMP-102');

        $this->actingAs($this->admin())->post(route('subjects.store'), [
            'code' => 'IS101',
            'name' => 'Introduction to Information Systems',
            'department_id' => $department->id,
            'units' => '3',
            'is_active' => '1',
            'instructor_id' => $firstInstructor->id,
            'academic_year_id' => $term['year']->id,
            'semester_id' => $term['semester']->id,
        ])->assertRedirect();

        $response = $this->actingAs($this->admin())->post(route('subjects.store'), [
            'code' => 'IS101',
            'name' => 'Introduction to Information Systems',
            'department_id' => $department->id,
            'units' => '3',
            'is_active' => '1',
            'instructor_id' => $secondInstructor->id,
            'academic_year_id' => $term['year']->id,
            'semester_id' => $term['semester']->id,
        ]);

        $this->assertSame(2, Subject::query()->where('code', 'IS101')->count());
        $response->assertRedirect();
    }

    public function test_admin_cannot_create_duplicate_subject_for_same_instructor(): void
    {
        $department = $this->department();
        $term = $this->term();
        $instructor = $this->instructor($department, 'EMP-103');

        $this->actingAs($this->admin())->post(route('subjects.store'), [
            'code' => 'IS101',
            'name' => 'Introduction to Information Systems',
            'department_id' => $department->id,
            'units' => '3',
            'is_active' => '1',
            'instructor_id' => $instructor->id,
            'academic_year_id' => $term['year']->id,
            'semester_id' => $term['semester']->id,
        ])->assertRedirect();

        $this->actingAs($this->admin())
            ->post(route('subjects.store'), [
                'code' => 'IS101',
                'name' => 'Introduction to Information Systems',
                'department_id' => $department->id,
                'units' => '3',
                'is_active' => '1',
                'instructor_id' => $instructor->id,
                'academic_year_id' => $term['year']->id,
                'semester_id' => $term['semester']->id,
            ])
            ->assertSessionHasErrors(['code', 'name']);
    }

    public function test_admin_cannot_reuse_code_without_instructor_when_subject_exists(): void
    {
        $department = $this->department();

        Subject::create([
            'department_id' => $department->id,
            'code' => 'IS101',
            'name' => 'Introduction to Information Systems',
            'units' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->post(route('subjects.store'), [
                'code' => 'IS101',
                'name' => 'Another Subject Name',
                'department_id' => $department->id,
                'units' => '3',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_view_and_edit_pages_are_available(): void
    {
        $admin = $this->admin();
        $subject = $this->subject();

        $this->actingAs($admin)
            ->get(route('subjects.show', $subject))
            ->assertOk()
            ->assertSee('Introduction to Information Systems');

        $this->actingAs($admin)
            ->get(route('subjects.edit', $subject))
            ->assertOk()
            ->assertSee('Save Changes');
    }

    public function test_admin_can_update_a_subject(): void
    {
        $subject = $this->subject();

        $this->actingAs($this->admin())
            ->put(route('subjects.update', $subject), [
                'code' => 'IS101',
                'name' => 'Intro to IS',
                'description' => 'Updated description',
                'department_id' => $subject->department_id,
                'units' => '4',
                'is_active' => '1',
            ])
            ->assertRedirect(route('subjects.show', $subject));

        $this->assertSame('Intro to IS', $subject->fresh()->name);
        $this->assertSame(4, $subject->fresh()->units);
    }

    public function test_non_admin_cannot_manage_subjects(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Student->value);

        $this->actingAs($user)
            ->get(route('subjects.create'))
            ->assertForbidden();
    }

    protected function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(UserRole::Admin->value);

        return $admin;
    }

    protected function department(): Department
    {
        return Department::create([
            'code' => 'CCIS',
            'name' => 'College of Computing',
            'is_active' => true,
        ]);
    }

    protected function subject(): Subject
    {
        return Subject::create([
            'department_id' => $this->department()->id,
            'code' => 'IS101',
            'name' => 'Introduction to Information Systems',
            'units' => 3,
            'is_active' => true,
        ]);
    }

    protected function term(): array
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
            'is_active' => true,
        ]);

        return compact('year', 'semester');
    }

    protected function instructor(Department $department, string $employeeId = 'EMP-100'): Instructor
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Instructor->value);

        return Instructor::create([
            'user_id' => $user->id,
            'employee_id' => $employeeId,
            'department_id' => $department->id,
            'is_active' => true,
        ]);
    }
}
