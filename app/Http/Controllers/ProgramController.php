<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Models\Department;
use App\Models\Program;
use App\Services\Academic\ProgramDeletionService;
use App\Services\AuditLogger;
use App\Support\YearLevelDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgramController extends Controller
{
    public function __construct(
        protected ProgramDeletionService $deletion,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $programs = Program::query()
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

        $deletionAnalyses = $programs->getCollection()
            ->mapWithKeys(fn (Program $program) => [$program->id => $this->deletion->analyze($program)]);

        return view('pages.programs.index', compact('programs', 'search', 'deletionAnalyses'));
    }

    public function create(): View
    {
        return view('pages.programs.create', $this->formOptions());
    }

    public function store(StoreProgramRequest $request): RedirectResponse
    {
        $program = Program::create($request->validated());
        YearLevelDefaults::ensureForProgram($program);

        return redirect()
            ->route('programs.show', $program)
            ->with('status', 'Program created successfully.');
    }

    public function show(Program $program): View
    {
        $program->load(['department', 'yearLevels']);

        return view('pages.programs.show', compact('program'));
    }

    public function edit(Program $program): View
    {
        return view('pages.programs.edit', array_merge(
            ['program' => $program],
            $this->formOptions()
        ));
    }

    public function update(UpdateProgramRequest $request, Program $program): RedirectResponse
    {
        $program->update($request->validated());

        return redirect()
            ->route('programs.show', $program)
            ->with('status', 'Program updated successfully.');
    }

    public function destroy(Request $request, Program $program): RedirectResponse
    {
        $this->authorize('delete', $program);

        $analysis = $this->deletion->analyze($program);

        if (! $this->deletion->delete($program)) {
            $this->auditLogger->log(
                $request->user(),
                'delete_blocked',
                'programs',
                Program::class,
                $program->id,
                [
                    'name' => $program->name,
                    'code' => $program->code,
                    'role' => $request->user()?->getRoleNames()->first(),
                    'succeeded' => false,
                    'reason' => $analysis->blockedMessage(),
                    'blockers' => $analysis->blockers,
                ],
            );

            return redirect()
                ->route('programs.index')
                ->with('error', $analysis->blockedMessage());
        }

        $this->auditLogger->log(
            $request->user(),
            'delete',
            'programs',
            Program::class,
            $program->id,
            [
                'name' => $program->name,
                'code' => $program->code,
                'role' => $request->user()?->getRoleNames()->first(),
                'succeeded' => true,
            ],
        );

        return redirect()
            ->route('programs.index')
            ->with('status', 'Program deleted successfully.');
    }

    protected function formOptions(): array
    {
        return [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
