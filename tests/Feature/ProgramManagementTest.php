<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Admin->value);
        Role::findOrCreate(UserRole::Student->value);
    }

    public function test_admin_can_view_programs_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('programs.index'))
            ->assertOk()
            ->assertSee('Create Program');
    }

    public function test_admin_can_create_a_program(): void
    {
        $department = $this->department();

        $response = $this->actingAs($this->admin())->post(route('programs.store'), [
            'code' => 'bsis',
            'name' => 'BS Information Systems',
            'description' => 'Information systems program',
            'department_id' => $department->id,
            'is_active' => '1',
        ]);

        $program = Program::query()->where('code', 'BSIS')->first();

        $this->assertNotNull($program);
        $response->assertRedirect(route('programs.show', $program));
    }

    public function test_view_and_edit_pages_are_available(): void
    {
        $admin = $this->admin();
        $program = $this->program();

        $this->actingAs($admin)
            ->get(route('programs.show', $program))
            ->assertOk()
            ->assertSee('BS Information Systems');

        $this->actingAs($admin)
            ->get(route('programs.edit', $program))
            ->assertOk()
            ->assertSee('Save Changes');
    }

    public function test_admin_can_update_a_program(): void
    {
        $program = $this->program();

        $this->actingAs($this->admin())
            ->put(route('programs.update', $program), [
                'code' => 'BSIS',
                'name' => 'Bachelor of Science in Information Systems',
                'description' => 'Updated description',
                'department_id' => $program->department_id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('programs.show', $program));

        $this->assertSame('Bachelor of Science in Information Systems', $program->fresh()->name);
    }

    public function test_non_admin_cannot_manage_programs(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Student->value);

        $this->actingAs($user)
            ->get(route('programs.create'))
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

    protected function program(): Program
    {
        return Program::create([
            'department_id' => $this->department()->id,
            'code' => 'BSIS',
            'name' => 'BS Information Systems',
            'is_active' => true,
        ]);
    }
}
