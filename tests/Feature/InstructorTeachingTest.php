<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorTeachingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Instructor->value);
        Role::findOrCreate(UserRole::Student->value);
        Role::findOrCreate(UserRole::Admin->value);
    }

    public function test_instructor_can_view_my_classes_index(): void
    {
        $structure = $this->academicStructure();
        $instructor = $this->assignInstructorToSubject($structure);

        $this->actingAs($instructor->user)
            ->get(route('instructor.teaching.index'))
            ->assertOk()
            ->assertSee('My Classes')
            ->assertSee($structure['subject']->name);
    }

    public function test_instructor_can_view_assigned_subject_sections(): void
    {
        $structure = $this->academicStructure();
        $instructor = $this->assignInstructorToSubject($structure);

        $this->actingAs($instructor->user)
            ->get(route('instructor.teaching.show', [
                'subject' => $structure['subject'],
                'academic_year_id' => $structure['year']->id,
                'semester_id' => $structure['semester']->id,
            ]))
            ->assertOk()
            ->assertSee($structure['subject']->name)
            ->assertSee($structure['sectionA']->name);
    }

    public function test_instructor_can_view_section_roster(): void
    {
        $structure = $this->academicStructure();
        $instructor = $this->assignInstructorToSubject($structure);
        $student = $this->student($structure['sectionA']);

        $this->actingAs($instructor->user)
            ->get(route('instructor.teaching.section', [
                'subject' => $structure['subject'],
                'section' => $structure['sectionA'],
                'academic_year_id' => $structure['year']->id,
                'semester_id' => $structure['semester']->id,
            ]))
            ->assertOk()
            ->assertSee('Student Roster')
            ->assertSee($student->student_id)
            ->assertSee($student->user->fullName());
    }

    public function test_instructor_cannot_view_unassigned_subject(): void
    {
        $structure = $this->academicStructure();
        $instructor = $this->instructor($structure['department']);

        $this->actingAs($instructor->user)
            ->get(route('instructor.teaching.show', [
                'subject' => $structure['subject'],
                'academic_year_id' => $structure['year']->id,
                'semester_id' => $structure['semester']->id,
            ]))
            ->assertForbidden();
    }

    public function test_instructor_dashboard_shows_assigned_classes(): void
    {
        $structure = $this->academicStructure();
        $instructor = $this->assignInstructorToSubject($structure);

        $this->actingAs($instructor->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Classes')
            ->assertSee($structure['subject']->code);
    }

    public function test_student_cannot_access_my_classes(): void
    {
        $structure = $this->academicStructure();
        $student = $this->student($structure['sectionA']);

        $this->actingAs($student->user)
            ->get(route('instructor.teaching.index'))
            ->assertForbidden();
    }

    protected function assignInstructorToSubject(array $structure): Instructor
    {
        $instructor = $this->instructor($structure['department']);

        DB::table('subject_instructor')->insert([
            'subject_id' => $structure['subject']->id,
            'instructor_id' => $instructor->id,
            'section_id' => $structure['sectionA']->id,
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $instructor;
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
            'name' => '1st Semester',
            'order' => 1,
            'is_current' => true,
            'is_active' => true,
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
            'name' => '1st Year',
            'level' => 1,
            'is_active' => true,
        ]);

        $sectionA = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 1A',
            'code' => 'BSIS-1A',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'department_id' => $department->id,
            'code' => 'IS101',
            'name' => 'Introduction to Information Systems',
            'units' => 3,
            'is_active' => true,
        ]);

        return compact('year', 'semester', 'department', 'program', 'yearLevel', 'sectionA', 'subject');
    }
}
