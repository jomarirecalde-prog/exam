<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Admin->value);
        Role::findOrCreate(UserRole::Instructor->value);
        Role::findOrCreate(UserRole::Student->value);
    }

    public function test_admin_can_view_instructors_index(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('instructors.index'))
            ->assertOk()
            ->assertSee('Add Instructor');
    }

    public function test_admin_can_create_an_instructor(): void
    {
        $admin = $this->admin();
        $department = $this->department();

        $response = $this->actingAs($admin)->post(route('instructors.store'), [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana.reyes@exam.local',
            'employee_id' => 'EMP-200',
            'department_id' => $department->id,
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_active' => '1',
        ]);

        $instructor = Instructor::query()->where('employee_id', 'EMP-200')->first();

        $this->assertNotNull($instructor);
        $response->assertRedirect(route('instructors.show', $instructor));
        $this->assertDatabaseHas('users', [
            'email' => 'ana.reyes@exam.local',
            'first_name' => 'Ana',
        ]);
        $this->assertTrue($instructor->user->hasRole(UserRole::Instructor->value));
    }

    public function test_view_and_edit_pages_are_available(): void
    {
        $admin = $this->admin();
        $instructor = $this->instructor();

        $this->actingAs($admin)
            ->get(route('instructors.show', $instructor))
            ->assertOk()
            ->assertSee('Juan Dela Cruz');

        $this->actingAs($admin)
            ->get(route('instructors.edit', $instructor))
            ->assertOk()
            ->assertSee('Save Changes');
    }

    public function test_admin_can_update_an_instructor(): void
    {
        $admin = $this->admin();
        $instructor = $this->instructor();
        $department = $this->department();

        $this->actingAs($admin)
            ->put(route('instructors.update', $instructor), [
                'first_name' => 'Juan',
                'last_name' => 'Cruz',
                'email' => 'juan.cruz@exam.local',
                'employee_id' => 'EMP-001',
                'department_id' => $department->id,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => '1',
            ])
            ->assertRedirect(route('instructors.show', $instructor));

        $this->assertSame('Cruz', $instructor->user()->first()->last_name);
    }

    public function test_non_admin_cannot_manage_instructors(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Student->value);

        $this->actingAs($user)
            ->get(route('instructors.create'))
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
        return Department::query()->first() ?? Department::create([
            'code' => 'CCIS',
            'name' => 'College of Computing and Information Sciences',
            'is_active' => true,
        ]);
    }

    protected function instructor(): Instructor
    {
        $department = $this->department();
        $user = User::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'name' => 'Juan Dela Cruz',
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::Instructor->value);

        return Instructor::create([
            'user_id' => $user->id,
            'employee_id' => 'EMP-001',
            'department_id' => $department->id,
            'is_active' => true,
        ]);
    }
}
