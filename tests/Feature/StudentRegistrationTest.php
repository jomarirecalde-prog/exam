<?php

namespace Tests\Feature;

use App\Enums\ExaminationAccessMode;
use App\Enums\ExamStatus;
use App\Enums\StudentRegistrationStatus;
use App\Enums\StudentSubjectEnrollmentStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Examination;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubject;
use App\Models\Instructor;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use App\Models\YearLevel;
use App\Notifications\NewStudentRegistrationNotification;
use App\Notifications\StudentRegistrationApprovedNotification;
use App\Services\Examinations\ExaminationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->assertSee('Personal Information')
            ->assertSee('Select Your Enrolled Subjects');
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
        $this->assertCount(2, $student->subjectEnrollments);

        $response->assertRedirect(route('student-registration.confirmation', ['student' => $student->id]));

        Notification::assertSentTo($admin, NewStudentRegistrationNotification::class);
    }

    public function test_registration_requires_at_least_one_subject(): void
    {
        $structure = $this->academicStructure();
        $payload = $this->registrationPayload($structure);
        $payload['subject_offering_ids'] = [];

        $this->post(route('student-registration.store'), $payload)
            ->assertSessionHasErrors(['subject_offering_ids']);
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

    public function test_manipulated_subject_offering_ids_are_rejected(): void
    {
        $structure = $this->academicStructure();
        $payload = $this->registrationPayload($structure);
        $payload['subject_offering_ids'] = [99999];

        $response = $this->post(route('student-registration.store'), $payload);

        $response->assertSessionHasErrors();
        $this->assertTrue(
            $response->getSession()->get('errors')->has('subject_offering_ids')
            || $response->getSession()->get('errors')->has('subject_offering_ids.0')
        );
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
            ->assertSee('Approve')
            ->assertSee('Declared Subject Enrollment');

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

    public function test_admin_can_verify_subject_enrollment(): void
    {
        $structure = $this->academicStructure();
        $admin = $this->admin();

        $this->post(route('student-registration.store'), $this->registrationPayload($structure));
        $student = Student::query()->where('student_id', '2026-9999')->firstOrFail();
        $enrollment = $student->subjectEnrollments()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.student-registrations.subjects.verify', [$student, $enrollment]))
            ->assertRedirect(route('admin.student-registrations.show', $student));

        $this->assertSame(
            StudentSubjectEnrollmentStatus::Verified,
            $enrollment->fresh()->status,
        );
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

    public function test_subjects_lookup_returns_recommended_and_other_offerings(): void
    {
        $structure = $this->academicStructure();

        $this->getJson(route('student-registration.subjects', [
            'section_id' => $structure['section']->id,
            'department_id' => $structure['department']->id,
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel']->id,
        ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $structure['offerings'][0]->id])
            ->assertJsonFragment(['instructor_name' => 'Juan Dela Cruz']);
    }

    public function test_student_cannot_access_exam_for_different_subject_offering(): void
    {
        $structure = $this->academicStructure();
        $student = $this->approvedStudent($structure, [$structure['offerings'][0]->id]);

        StudentSubject::query()
            ->where('student_id', $student->id)
            ->update([
                'status' => StudentSubjectEnrollmentStatus::Verified,
                'verified_at' => now(),
            ]);

        $exam = Examination::create([
            'title' => 'IS 103 Exam — Other Section',
            'subject_id' => $structure['subjects'][1]->id,
            'subject_offering_id' => $structure['offerings'][2]->id,
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'examination_period' => 'MIDTERM',
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'status' => ExamStatus::Published,
            'access_mode' => ExaminationAccessMode::SubjectOnly,
        ]);

        $service = app(ExaminationAccessService::class);

        $this->assertFalse($service->canTake($student->user, $exam));
    }

    public function test_student_cannot_access_exam_without_subject_enrollment(): void
    {
        $structure = $this->academicStructure();
        $student = $this->approvedStudent($structure, [$structure['offerings'][0]->id]);

        $exam = Examination::create([
            'title' => 'IS 103 Exam',
            'subject_id' => $structure['subjects'][1]->id,
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'examination_period' => 'MIDTERM',
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'status' => ExamStatus::Published,
            'access_mode' => ExaminationAccessMode::SubjectOnly,
        ]);

        $service = app(ExaminationAccessService::class);

        $this->assertFalse($service->canTake($student->user, $exam));
    }

    public function test_student_can_access_exam_when_enrolled_in_subject(): void
    {
        $structure = $this->academicStructure();
        $student = $this->approvedStudent($structure, [$structure['offerings'][0]->id]);

        StudentSubject::query()
            ->where('student_id', $student->id)
            ->update([
                'status' => StudentSubjectEnrollmentStatus::Verified,
                'verified_at' => now(),
            ]);

        $exam = Examination::create([
            'title' => 'IS 101 Exam',
            'subject_id' => $structure['subjects'][0]->id,
            'academic_year_id' => $structure['year']->id,
            'semester_id' => $structure['semester']->id,
            'examination_period' => 'MIDTERM',
            'duration_minutes' => 60,
            'passing_percentage' => 75,
            'status' => ExamStatus::Published,
            'access_mode' => ExaminationAccessMode::SubjectOnly,
        ]);

        $service = app(ExaminationAccessService::class);

        $this->assertTrue($service->canTake($student->user, $exam));
    }

    protected function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true, 'email' => 'admin@exam.local']);
        $admin->assignRole(UserRole::Admin->value);

        return $admin;
    }

    protected function approvedStudent(array $structure, array $offeringIds): Student
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(UserRole::Student->value);

        $student = Student::create([
            'user_id' => $user->id,
            'student_id' => '2026-1001',
            'program_id' => $structure['program']->id,
            'year_level_id' => $structure['yearLevel']->id,
            'section_id' => $structure['section']->id,
            'registration_status' => StudentRegistrationStatus::Approved,
            'registered_at' => now(),
            'is_active' => true,
        ]);

        foreach ($offeringIds as $offeringId) {
            $offering = SubjectOffering::query()->findOrFail($offeringId);

            StudentSubject::create([
                'student_id' => $student->id,
                'subject_id' => $offering->subject_id,
                'subject_offering_id' => $offering->id,
                'academic_year_id' => $structure['year']->id,
                'semester_id' => $structure['semester']->id,
                'status' => StudentSubjectEnrollmentStatus::PendingVerification,
            ]);
        }

        return $student->fresh(['user']);
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
            'subject_offering_ids' => collect($structure['offerings'])
                ->filter(fn ($offering) => (int) $offering->section_id === (int) $structure['section']->id)
                ->pluck('id')
                ->all(),
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

        $subjects = collect([
            ['code' => 'IS 101', 'name' => 'Fundamentals of Information Systems'],
            ['code' => 'IS 103', 'name' => 'Database Management Systems'],
        ])->map(fn (array $data) => Subject::create([
            'department_id' => $department->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'units' => 3,
            'is_active' => true,
        ]));

        $instructorUser = User::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'name' => 'Prof. Juan Dela Cruz',
        ]);

        $instructor = Instructor::create([
            'user_id' => $instructorUser->id,
            'employee_id' => 'EMP-001',
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $otherInstructorUser = User::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'name' => 'Prof. Maria Santos',
        ]);

        $otherInstructor = Instructor::create([
            'user_id' => $otherInstructorUser->id,
            'employee_id' => 'EMP-002',
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $sectionB = Section::create([
            'program_id' => $program->id,
            'year_level_id' => $yearLevel->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'name' => 'BSIS 2B',
            'code' => 'BSIS-2B',
            'is_active' => true,
        ]);

        $offerings = collect();

        foreach ($subjects as $index => $subject) {
            DB::table('subject_section')->insert([
                'subject_id' => $subject->id,
                'section_id' => $section->id,
                'academic_year_id' => $year->id,
                'semester_id' => $semester->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $offerings->push(SubjectOffering::create([
                'subject_id' => $subject->id,
                'instructor_id' => $instructor->id,
                'section_id' => $section->id,
                'academic_year_id' => $year->id,
                'semester_id' => $semester->id,
                'is_active' => true,
            ]));
        }

        DB::table('subject_section')->insert([
            'subject_id' => $subjects[1]->id,
            'section_id' => $sectionB->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $offerings->push(SubjectOffering::create([
            'subject_id' => $subjects[1]->id,
            'instructor_id' => $otherInstructor->id,
            'section_id' => $sectionB->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'is_active' => true,
        ]));

        return compact('year', 'semester', 'department', 'program', 'yearLevel', 'section', 'sectionB') + [
            'subjects' => $subjects->all(),
            'offerings' => $offerings->all(),
        ];
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
