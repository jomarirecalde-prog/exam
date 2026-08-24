<?php

namespace App\Http\Controllers;

use App\Enums\StudentRegistrationStatus;
use App\Http\Requests\RejectStudentRegistrationRequest;
use App\Models\Department;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Models\YearLevel;
use App\Services\AuditLogger;
use App\Services\Students\StudentRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStudentRegistrationController extends Controller
{
    public function __construct(
        protected StudentRegistrationService $registrations,
        protected AuditLogger $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'year_level_id' => ['nullable', 'integer', 'exists:year_levels,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $registrations = Student::query()
            ->with(['user', 'program.department', 'yearLevel', 'section'])
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('registration_status', $filters['status']))
            ->when(filled($filters['department_id'] ?? null), function ($query) use ($filters) {
                $query->whereHas('program', fn ($q) => $q->where('department_id', $filters['department_id']));
            })
            ->when(filled($filters['program_id'] ?? null), fn ($query) => $query->where('program_id', $filters['program_id']))
            ->when(filled($filters['year_level_id'] ?? null), fn ($query) => $query->where('year_level_id', $filters['year_level_id']))
            ->when(filled($filters['section_id'] ?? null), fn ($query) => $query->where('section_id', $filters['section_id']))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters) {
                $search = $filters['q'];
                $query->where(function ($query) use ($search) {
                    $query->where('student_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('email', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->whereNotNull('registered_at')
            ->latest('registered_at')
            ->paginate(15)
            ->withQueryString();

        return view('pages.admin.student-registrations.index', [
            'registrations' => $registrations,
            'filters' => $filters,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'programs' => Program::query()->where('is_active', true)->orderBy('name')->get(),
            'yearLevels' => YearLevel::query()->where('is_active', true)->orderBy('level')->get(),
            'sections' => Section::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Student $student): View
    {
        abort_unless($student->registered_at !== null, 404);

        $student->load(['user', 'program.department', 'yearLevel', 'section', 'approver']);

        return view('pages.admin.student-registrations.show', compact('student'));
    }

    public function approve(Request $request, Student $student): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['superadmin', 'admin']), 403);
        abort_unless($student->registration_status === StudentRegistrationStatus::Pending, 422);

        $this->registrations->approve($student, $request->user());

        $this->audit->log(
            $request->user(),
            'approve',
            'student_registrations',
            Student::class,
            $student->id,
            ['student_id' => $student->student_id],
        );

        return redirect()
            ->route('admin.student-registrations.show', $student)
            ->with('status', 'Student registration approved.');
    }

    public function reject(RejectStudentRegistrationRequest $request, Student $student): RedirectResponse
    {
        abort_unless($student->registration_status === StudentRegistrationStatus::Pending, 422);

        $this->registrations->reject($student, $request->user(), $request->validated('rejection_reason'));

        $this->audit->log(
            $request->user(),
            'reject',
            'student_registrations',
            Student::class,
            $student->id,
            ['student_id' => $student->student_id],
        );

        return redirect()
            ->route('admin.student-registrations.show', $student)
            ->with('status', 'Student registration rejected.');
    }
}
