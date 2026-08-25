<?php

namespace App\Http\Controllers;

use App\Enums\StudentRegistrationStatus;
use App\Enums\UserRole;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Student;
use App\Services\Students\AcademicLookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(
        protected AcademicLookupService $lookup,
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $students = Student::query()
            ->with(['user', 'program', 'section', 'yearLevel'])
            ->where('registration_status', StudentRegistrationStatus::Approved)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('student_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('program', fn ($query) => $query->where('code', 'like', "%{$search}%"))
                        ->orWhereHas('section', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('pages.students.index', compact('students', 'search'));
    }

    public function show(Student $student): View
    {
        abort_unless($student->registration_status === StudentRegistrationStatus::Approved, 404);

        $student->load(['user', 'program.department', 'yearLevel', 'section', 'approver']);

        return view('pages.students.show', compact('student'));
    }

    public function edit(Student $student): View
    {
        abort_unless($student->registration_status === StudentRegistrationStatus::Approved, 404);

        $student->load(['user', 'program.department']);

        $departmentId = (int) old('department_id', $student->program?->department_id);
        $programId = (int) old('program_id', $student->program_id);
        $yearLevelId = (int) old('year_level_id', $student->year_level_id);

        return view('pages.students.edit', [
            'student' => $student,
            'departments' => $this->lookup->activeDepartments(),
            'programs' => $departmentId ? $this->lookup->programsForDepartment($departmentId) : collect(),
            'yearLevels' => $programId ? $this->lookup->yearLevelsForProgram($programId) : collect(),
            'sections' => ($programId && $yearLevelId)
                ? $this->lookup->sectionsForProgramAndYearLevel($programId, $yearLevelId)
                : collect(),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        abort_unless($student->registration_status === StudentRegistrationStatus::Approved, 404);

        DB::transaction(function () use ($request, $student) {
            $data = $request->validated();
            $user = $student->user;

            $name = trim(collect([
                $data['first_name'],
                $data['middle_name'] ?? null,
                $data['last_name'],
            ])->filter()->implode(' '));

            $payload = [
                'name' => $name,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'suffix' => $data['suffix'] ?? null,
                'email' => $data['email'],
                'is_active' => $data['is_active'],
            ];

            if (filled($data['password'] ?? null)) {
                $payload['password'] = $data['password'];
            }

            $user->fill($payload)->save();

            if (! $user->hasRole(UserRole::Student->value)) {
                $user->assignRole(UserRole::Student->value);
            }

            $student->update([
                'student_id' => $data['student_id'],
                'phone' => $data['phone'],
                'sex' => $data['sex'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'home_address' => $data['home_address'] ?? null,
                'program_id' => $data['program_id'],
                'year_level_id' => $data['year_level_id'],
                'section_id' => $data['section_id'],
                'is_active' => $data['is_active'],
            ]);
        });

        return redirect()
            ->route('students.show', $student)
            ->with('status', 'Student updated successfully.');
    }
}
