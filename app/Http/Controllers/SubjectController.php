<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use App\Services\Students\AcademicLookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $subjects = Subject::query()
            ->with('department')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('department', function ($query) use ($search) {
                            $query->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('pages.subjects.index', compact('subjects', 'search'));
    }

    public function create(): View
    {
        return view('pages.subjects.create', $this->formOptions());
    }

    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $subject = DB::transaction(function () use ($request) {
            $subject = Subject::create($request->safe()->only([
                'code',
                'name',
                'description',
                'department_id',
                'units',
                'is_active',
            ]));

            if ($request->filled('instructor_id')) {
                $subject->instructors()->attach($request->input('instructor_id'), [
                    'section_id' => $request->input('section_id'),
                    'academic_year_id' => $request->input('academic_year_id'),
                    'semester_id' => $request->input('semester_id'),
                ]);
            }

            return $subject;
        });

        return redirect()
            ->route('subjects.show', $subject)
            ->with('status', 'Subject created successfully.');
    }

    public function show(Subject $subject): View
    {
        $subject->load('department');

        $instructorAssignments = $subject->instructors()
            ->with('user')
            ->withPivot(['section_id', 'academic_year_id', 'semester_id'])
            ->get();

        $sections = Section::query()
            ->whereIn('id', $instructorAssignments->pluck('pivot.section_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $academicYears = AcademicYear::query()
            ->whereIn('id', $instructorAssignments->pluck('pivot.academic_year_id')->unique())
            ->get()
            ->keyBy('id');

        $semesters = Semester::query()
            ->whereIn('id', $instructorAssignments->pluck('pivot.semester_id')->unique())
            ->get()
            ->keyBy('id');

        return view('pages.subjects.show', compact(
            'subject',
            'instructorAssignments',
            'sections',
            'academicYears',
            'semesters',
        ));
    }

    public function edit(Subject $subject): View
    {
        return view('pages.subjects.edit', array_merge(
            ['subject' => $subject],
            $this->formOptions()
        ));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return redirect()
            ->route('subjects.show', $subject)
            ->with('status', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->update(['is_active' => false]);

        return redirect()
            ->route('subjects.index')
            ->with('status', 'Subject deactivated.');
    }

    protected function formOptions(): array
    {
        $lookup = app(AcademicLookupService::class);
        $currentYear = $lookup->currentAcademicYear();
        $currentSemester = $lookup->currentSemester($currentYear);

        return [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'instructors' => Instructor::query()
                ->with('user')
                ->where('is_active', true)
                ->orderBy('employee_id')
                ->get(),
            'academicYears' => AcademicYear::query()->orderByDesc('is_current')->orderByDesc('name')->get(),
            'semesters' => Semester::query()->orderBy('order')->get(['id', 'academic_year_id', 'name']),
            'sections' => Section::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'academic_year_id', 'semester_id']),
            'defaultAcademicYearId' => $currentYear?->id,
            'defaultSemesterId' => $currentSemester?->id,
        ];
    }
}
