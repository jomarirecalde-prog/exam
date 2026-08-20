<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
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
}
