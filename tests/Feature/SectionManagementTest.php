<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SectionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Admin->value);
        Role::findOrCreate(UserRole::Student->value);
    }

    public function test_admin_can_view_sections_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('sections.index'))
            ->assertOk()
            ->assertSee('Create Section');
    }

    public function test_admin_can_create_a_section(): void
    {
        $admin = $this->admin();
        $structure = $this->academicStructure();

        $response = $this->actingAs($admin)->post(route('sections.store'), [
            'name' => 'BSIS 1B',
            'code' => 'BSIS-1B',
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel']->id,
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'is_active' => '1',
        ]);

        $section = Section::query()->where('code', 'BSIS-1B')->first();

        $this->assertNotNull($section);
        $response->assertRedirect(route('sections.show', $section));
    }

    public function test_view_and_edit_pages_are_available(): void
    {
        $admin = $this->admin();
        $section = $this->academicStructure()['section'];

        $this->actingAs($admin)
            ->get(route('sections.show', $section))
            ->assertOk()
            ->assertSee('BSIS 1A');

        $this->actingAs($admin)
            ->get(route('sections.edit', $section))
            ->assertOk()
            ->assertSee('Save Changes');
    }

    public function test_admin_can_update_a_section(): void
    {
        $admin = $this->admin();
        $structure = $this->academicStructure();
        $section = $structure['section'];

        $this->actingAs($admin)
            ->put(route('sections.update', $section), [
                'name' => 'BSIS 1A-Updated',
                'code' => 'BSIS-1A',
                'program_id' => $structure['program']->id,
                'year_level_id' => $structure['yearLevel']->id,
                'academic_year_id' => $structure['year']->id,
                'semester_id' => $structure['semester']->id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('sections.show', $section));

        $this->assertSame('BSIS 1A-Updated', $section->fresh()->name);
    }

    public function test_non_admin_cannot_manage_sections(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Student->value);

        $this->actingAs($user)
            ->get(route('sections.create'))
            ->assertForbidden();
    }

    protected function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(UserRole::Admin->value);

        return $admin;
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
            'name' => 'Bachelor of Science in Information Systems',
            'is_active' => true,
        ]);

        $yearLevel = YearLevel::create([
            'program_id' => $program->id,
            'name' => '1st Year',
            'level' => 1,
            'is_active' => true,
        ]);

        $section = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 1A',
            'code' => 'BSIS-1A',
            'is_active' => true,
        ]);

        return compact('year', 'semester', 'department', 'program', 'yearLevel', 'section');
    }
}
