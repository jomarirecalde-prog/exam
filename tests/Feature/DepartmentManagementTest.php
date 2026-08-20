<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Admin->value);
        Role::findOrCreate(UserRole::Student->value);
    }

    public function test_admin_can_view_departments_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Create Department');
    }

    public function test_admin_can_create_a_department(): void
    {
        $response = $this->actingAs($this->admin())->post(route('departments.store'), [
            'code' => 'ccis',
            'name' => 'College of Computing and Information Sciences',
            'description' => 'Computing programs',
            'is_active' => '1',
        ]);

        $department = Department::query()->where('code', 'CCIS')->first();

        $this->assertNotNull($department);
        $response->assertRedirect(route('departments.show', $department));
    }

    public function test_view_and_edit_pages_are_available(): void
    {
        $admin = $this->admin();
        $department = $this->department();

        $this->actingAs($admin)
            ->get(route('departments.show', $department))
            ->assertOk()
            ->assertSee('College of Computing');

        $this->actingAs($admin)
            ->get(route('departments.edit', $department))
            ->assertOk()
            ->assertSee('Save Changes');
    }

    public function test_admin_can_update_a_department(): void
    {
        $department = $this->department();

        $this->actingAs($this->admin())
            ->put(route('departments.update', $department), [
                'code' => 'CCIS',
                'name' => 'College of Computing',
                'description' => 'Updated description',
                'is_active' => '1',
            ])
            ->assertRedirect(route('departments.show', $department));

        $this->assertSame('Updated description', $department->fresh()->description);
    }

    public function test_non_admin_cannot_manage_departments(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Student->value);

        $this->actingAs($user)
            ->get(route('departments.create'))
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
}
