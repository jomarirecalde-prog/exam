<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRegistrationRequest;
use App\Services\Students\AcademicLookupService;
use App\Services\Students\StudentRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentRegistrationController extends Controller
{
    public function __construct(
        protected AcademicLookupService $academic,
        protected StudentRegistrationService $registrations,
    ) {
    }

    public function create(): View
    {
        return view('pages.student-registration.create', [
            'departments' => $this->academic->activeDepartments(),
        ]);
    }

    public function store(StoreStudentRegistrationRequest $request): RedirectResponse
    {
        $student = $this->registrations->register($request->validated());

        return redirect()
            ->route('student-registration.confirmation', ['student' => $student->id])
            ->with('registered_student_id', $student->student_id);
    }

    public function confirmation(Request $request, int $student): View
    {
        $maskedId = session('registered_student_id');

        if (! $maskedId) {
            abort(404);
        }

        return view('pages.student-registration.confirmation', [
            'maskedStudentId' => str_repeat('•', max(0, strlen($maskedId) - 4)).substr($maskedId, -4),
        ]);
    }

    public function departments(): JsonResponse
    {
        return response()->json([
            'departments' => $this->academic->activeDepartments(),
        ]);
    }

    public function programs(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ]);

        return response()->json([
            'programs' => $this->academic->programsForDepartment((int) $data['department_id']),
        ]);
    }

    public function yearLevels(Request $request): JsonResponse
    {
        $data = $request->validate([
            'program_id' => ['required', 'integer', 'exists:programs,id'],
        ]);

        return response()->json([
            'year_levels' => $this->academic->yearLevelsForProgram((int) $data['program_id']),
        ]);
    }

    public function sections(Request $request): JsonResponse
    {
        $data = $request->validate([
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'year_level_id' => ['required', 'integer', 'exists:year_levels,id'],
        ]);

        return response()->json([
            'sections' => $this->academic->sectionsForProgramAndYearLevel(
                (int) $data['program_id'],
                (int) $data['year_level_id'],
            ),
        ]);
    }
}
