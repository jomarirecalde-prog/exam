<?php

namespace App\Services\Students;

use App\Enums\StudentRegistrationStatus;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use App\Notifications\NewStudentRegistrationNotification;
use App\Notifications\StudentRegistrationApprovedNotification;
use App\Services\Google\LinkedAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

class StudentRegistrationService
{
    public function __construct(
        protected AcademicLookupService $academic,
        protected StudentSubjectEnrollmentService $subjectEnrollments,
        protected LinkedAccountService $linkedAccounts,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): Student
    {
        $this->assertAcademicChain(
            (int) $data['department_id'],
            (int) $data['program_id'],
            (int) $data['year_level_id'],
            (int) $data['section_id'],
        );

        $offeringIds = $this->subjectEnrollments->validateOfferingIds($data['subject_offering_ids'] ?? [])
            ->pluck('id')
            ->all();

        $student = DB::transaction(function () use ($data, $offeringIds) {
            $firstName = trim($data['first_name']);
            $lastName = trim($data['last_name']);
            $middleName = filled($data['middle_name'] ?? null) ? trim($data['middle_name']) : null;
            $suffix = filled($data['suffix'] ?? null) ? trim($data['suffix']) : null;
            $name = trim(collect([$firstName, $middleName, $lastName, $suffix])->filter()->implode(' '));

            $user = User::create([
                'name' => $name,
                'username' => $this->uniqueUsername($data['student_id'], $data['email']),
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'suffix' => $suffix,
                'email' => $data['email'],
                'password' => $data['password'],
                'password_login_enabled' => $data['password_login_enabled'] ?? true,
                'is_active' => false,
            ]);

            Role::findOrCreate(UserRole::Student->value);
            $user->assignRole(UserRole::Student->value);

            $student = Student::create([
                'user_id' => $user->id,
                'student_id' => trim($data['student_id']),
                'phone' => trim($data['phone']),
                'sex' => filled($data['sex'] ?? null) ? $data['sex'] : null,
                'date_of_birth' => filled($data['date_of_birth'] ?? null) ? $data['date_of_birth'] : null,
                'home_address' => filled($data['home_address'] ?? null) ? trim($data['home_address']) : null,
                'program_id' => $data['program_id'],
                'year_level_id' => $data['year_level_id'],
                'section_id' => $data['section_id'],
                'is_active' => false,
                'registration_status' => StudentRegistrationStatus::Pending,
                'registered_at' => now(),
            ]);

            $this->syncSectionEnrollment($student);
            $this->subjectEnrollments->syncDeclaredEnrollments(
                $student,
                $offeringIds,
                actor: $user,
            );

            return $student->load([
                'user',
                'program.department',
                'yearLevel',
                'section',
                'subjectEnrollments.subject',
                'subjectEnrollments.subjectOffering.instructor.user',
                'subjectEnrollments.subjectOffering.section',
            ]);
        });

        $this->notifyAdministrators($student);

        return $student;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $googleProfile
     */
    public function registerWithGoogle(array $data, array $googleProfile): Student
    {
        $data['email'] = strtolower(trim($googleProfile['email'] ?? $data['email'] ?? ''));

        $student = $this->register(array_merge($data, [
            'password' => filled($data['password'] ?? null) ? $data['password'] : Str::password(32),
            'password_login_enabled' => filled($data['password'] ?? null),
            'email' => $data['email'],
        ]));

        $this->linkedAccounts->linkGoogleProfile($student->user, [
            'provider_account_id' => $googleProfile['provider_account_id'],
            'email' => $googleProfile['email'],
            'name' => $googleProfile['name'] ?? $student->user->name,
            'avatar' => $googleProfile['avatar'] ?? null,
        ]);

        $student->user->forceFill(['email_verified_at' => now()])->save();

        return $student->fresh([
            'user.linkedAccounts',
            'program.department',
            'yearLevel',
            'section',
            'subjectEnrollments.subject',
            'subjectEnrollments.subjectOffering.instructor.user',
            'subjectEnrollments.subjectOffering.section',
        ]);
    }

    public function approve(Student $student, User $admin): Student
    {
        $student = DB::transaction(function () use ($student, $admin) {
            $student->update([
                'registration_status' => StudentRegistrationStatus::Approved,
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'rejection_reason' => null,
            ]);

            $student->user?->update([
                'is_active' => true,
                'email_verified_at' => $student->user->email_verified_at ?? now(),
            ]);

            return $student->fresh(['user', 'program.department', 'yearLevel', 'section', 'subjectEnrollments.subject']);
        });

        $this->notifyStudentOfApproval($student);

        return $student;
    }

    public function reject(Student $student, User $admin, ?string $reason = null): Student
    {
        return DB::transaction(function () use ($student, $admin, $reason) {
            $student->update([
                'registration_status' => StudentRegistrationStatus::Rejected,
                'is_active' => false,
                'approved_at' => null,
                'approved_by' => $admin->id,
                'rejection_reason' => filled($reason) ? trim($reason) : null,
            ]);

            $student->user?->update(['is_active' => false]);

            return $student->fresh(['user', 'program.department', 'yearLevel', 'section', 'subjectEnrollments.subject']);
        });
    }

    public function assertAcademicChain(int $departmentId, int $programId, int $yearLevelId, int $sectionId): void
    {
        if (! $this->academic->programBelongsToDepartment($programId, $departmentId)) {
            abort(422, 'The selected program does not belong to the chosen department.');
        }

        if (! $this->academic->yearLevelBelongsToProgram($yearLevelId, $programId)) {
            abort(422, 'The selected year level does not belong to the chosen program.');
        }

        if (! $this->academic->sectionBelongsToProgramAndYearLevel($sectionId, $programId, $yearLevelId)) {
            abort(422, 'The selected section is not valid for the chosen program and year level.');
        }
    }

    protected function syncSectionEnrollment(Student $student): void
    {
        $section = $student->section;

        if (! $section) {
            return;
        }

        $student->sections()->syncWithoutDetaching([
            $section->id => [
                'academic_year_id' => $section->academic_year_id,
                'semester_id' => $section->semester_id,
            ],
        ]);
    }

    protected function notifyAdministrators(Student $student): void
    {
        try {
            User::role([UserRole::Superadmin->value, UserRole::Admin->value])
                ->where('is_active', true)
                ->get()
                ->each(fn (User $admin) => $admin->notify(new NewStudentRegistrationNotification($student)));
        } catch (Throwable $exception) {
            Log::warning('Unable to notify administrators about student registration.', [
                'student_id' => $student->student_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function notifyStudentOfApproval(Student $student): void
    {
        try {
            $student->user?->notify(new StudentRegistrationApprovedNotification($student));
        } catch (Throwable $exception) {
            Log::warning('Unable to notify student about registration approval.', [
                'student_id' => $student->student_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function uniqueUsername(string $studentId, string $email): string
    {
        $base = Str::lower(preg_replace('/[^a-z0-9._-]+/i', '', str_replace(' ', '.', $studentId)))
            ?: Str::before($email, '@');
        $base = $base !== '' ? $base : 'student';

        $username = $base;
        $suffix = 1;

        while (User::withTrashed()->where('username', $username)->exists()) {
            $username = $base.$suffix;
            $suffix++;
        }

        return $username;
    }
}
