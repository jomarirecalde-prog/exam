<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSectionRequest;
use App\Http\Requests\UpdateSectionRequest;
use App\Models\AcademicYear;
use App\Models\Program;
use App\Models\Section;
use App\Models\Semester;
use App\Models\YearLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $sections = Section::query()
            ->with(['program', 'yearLevel', 'academicYear', 'semester'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('program', function ($query) use ($search) {
                            $query->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('pages.sections.index', compact('sections', 'search'));
    }

    public function create(): View
    {
        return view('pages.sections.create', $this->formOptions());
    }

    public function store(StoreSectionRequest $request): RedirectResponse
    {
        $section = Section::create($request->validated());

        return redirect()
            ->route('sections.show', $section)
            ->with('status', 'Section created successfully.');
    }

    public function show(Section $section): View
    {
        $section->load(['program.department', 'yearLevel', 'academicYear', 'semester']);

        return view('pages.sections.show', compact('section'));
    }

    public function edit(Section $section): View
    {
        return view('pages.sections.edit', array_merge(
            ['section' => $section],
            $this->formOptions()
        ));
    }

    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->validated());

        return redirect()
            ->route('sections.show', $section)
            ->with('status', 'Section updated successfully.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        $section->update(['is_active' => false]);

        return redirect()
            ->route('sections.index')
            ->with('status', 'Section deactivated.');
    }

    protected function formOptions(): array
    {
        return [
            'programs' => Program::query()->where('is_active', true)->orderBy('name')->get(),
            'yearLevels' => YearLevel::query()->orderBy('level')->get(['id', 'program_id', 'name']),
            'academicYears' => AcademicYear::query()->orderByDesc('is_current')->orderByDesc('name')->get(),
            'semesters' => Semester::query()->orderBy('order')->get(['id', 'academic_year_id', 'name']),
        ];
    }
}
