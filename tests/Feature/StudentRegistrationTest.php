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
use App\Notifications\NewStudentRegistrationNotification;
use App\Notifications\StudentRegistrationApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRole::Superadmin->value);
        Role::findOrCreate(UserRole::Admin->value);
        Role::findOrCreate(UserRole::Student->value);
    }

    public function test_homepage_shows_student_registration_cta(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Are you a student?')
            ->assertSee('Register as Student');
    }

    public function test_guest_can_view_registration_form(): void
    {
        $this->get(route('student-registration.create'))
            ->assertOk()
            ->assertSee('Create Student Account')
            ->assertSee('Personal Information');
    }

    public function test_student_can_register_with_valid_data(): void
    {
        Notification::fake();

        $structure = $this->academicStructure();
        $admin = $this->admin();

        $response = $this->post(route('student-registration.store'), $this->registrationPayload($structure));

        $student = Student::query()->where('student_id', '2026-9999')->first();

        $this->assertNotNull($student);
        $this->assertSame(StudentRegistrationStatus::Pending, $student->registration_status);
        $this->assertFalse($student->user->is_active);
        $this->assertTrue($student->user->hasRole(UserRole::Student->value));
        $this->assertTrue(Hash::check('Password123!', $student->user->password));

        $response->assertRedirect(route('student-registration.confirmation', ['student' => $student->id]));

        Notification::assertSentTo($admin, NewStudentRegistrationNotification::class);
    }

    public function test_duplicate_student_id_is_rejected(): void
    {
        $structure = $this->academicStructure();

        Student::create([
            'user_id' => User::factory()->create()->id,
            'student_id' => '2026-9999',
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel']->id,
            'section_id' => $structure['section']->id,
            'registration_status' => StudentRegistrationStatus::Approved,
            'registered_at' => now(),
        ]);

        $this->post(route('student-registration.store'), $this->registrationPayload($structure))
            ->assertSessionHasErrors(['student_id']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $structure = $this->academicStructure();

        User::factory()->create(['email' => 'new.student@exam.local']);

        $this->post(route('student-registration.store'), $this->registrationPayload($structure))
            ->assertSessionHasErrors(['email']);
    }

    public function test_manipulated_section_id_is_rejected(): void
    {
        $structure = $this->academicStructure();
        $otherStructure = $this->secondSectionStructure($structure);

        $payload = $this->registrationPayload($structure);
        $payload['section_id'] = $otherStructure['section']->id;

        $this->post(route('student-registration.store'), $payload)
            ->assertSessionHasErrors(['section_id']);
    }

    public function test_pending_student_cannot_sign_in(): void
    {
        $structure = $this->academicStructure();

        $this->post(route('student-registration.store'), $this->registrationPayload($structure));

        $component = Volt::test('pages.auth.login')
            ->set('form.email', 'new.student@exam.local')
            ->set('form.password', 'Password123!');

        $component->call('login');

        $component->assertHasErrors(['form.email']);
        $this->assertGuest();
    }

    public function test_admin_can_view_and_approve_registration(): void
    {
        Notification::fake();

        $structure = $this->academicStructure();
        $admin = $this->admin();

        $this->post(route('student-registration.store'), $this->registrationPayload($structure));
        $student = Student::query()->where('student_id', '2026-9999')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.student-registrations.index'))
            ->assertOk()
            ->assertSee('2026-9999');

        $this->actingAs($admin)
            ->get(route('admin.student-registrations.show', $student))
            ->assertOk()
            ->assertSee('Approve');

        $this->actingAs($admin)
            ->post(route('admin.student-registrations.approve', $student))
            ->assertRedirect(route('admin.student-registrations.show', $student));

        $student->refresh();

        $this->assertSame(StudentRegistrationStatus::Approved, $student->registration_status);
        $this->assertTrue($student->user->is_active);

        Notification::assertSentTo($student->user, StudentRegistrationApprovedNotification::class);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', 'new.student@exam.local')
            ->set('form.password', 'Password123!');

        $component->call('login');

        $component->assertHasNoErrors()->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_admin_can_reject_registration(): void
    {
        $structure = $this->academicStructure();
        $admin = $this->admin();

        $this->post(route('student-registration.store'), $this->registrationPayload($structure));
        $student = Student::query()->where('student_id', '2026-9999')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.student-registrations.reject', $student), [
                'rejection_reason' => 'Invalid documents submitted.',
            ])
            ->assertRedirect(route('admin.student-registrations.show', $student));

        $student->refresh();

        $this->assertSame(StudentRegistrationStatus::Rejected, $student->registration_status);
        $this->assertSame('Invalid documents submitted.', $student->rejection_reason);
    }

    public function test_sections_lookup_returns_filtered_sections(): void
    {
        $structure = $this->academicStructure();

        $this->getJson(route('student-registration.sections', [
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel']->id,
        ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $structure['section']->id]);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true, 'email' => 'admin@exam.local']);
        $admin->assignRole(UserRole::Admin->value);

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    protected function registrationPayload(array $structure): array
    {
        return [
            'first_name' => 'Jose',
            'middle_name' => 'M',
            'last_name' => 'Rizal',
            'suffix' => 'Jr.',
            'sex' => 'male',
            'date_of_birth' => '2004-01-15',
            'phone' => '09171234567',
            'email' => 'new.student@exam.local',
            'home_address' => 'Calamba, Laguna',
            'student_id' => '2026-9999',
            'department_id' => $structure['department']->id,
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel']->id,
            'section_id' => $structure['section']->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];
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

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    protected function secondSectionStructure(array $base): array
    {
        $yearLevel = YearLevel::create([
            'program_id' => $base['program']->id,
            'name' => '3rd Year',
            'level' => 3,
            'is_active' => true,
        ]);

        $section = Section::create([
            'program_id' => $base['program']->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $base['year']->id,
            'semester_id' => $base['semester']->id,
            'name' => 'BSIS 3A',
            'code' => 'BSIS-3A',
            'is_active' => true,
        ]);

        return compact('yearLevel', 'section');
    }
}
