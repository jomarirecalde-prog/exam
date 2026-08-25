<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\Academic\DepartmentDeletionService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(
        protected DepartmentDeletionService $deletion,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $departments = Department::query()
            ->withCount('programs')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $deletionAnalyses = $departments->getCollection()
            ->mapWithKeys(fn (Department $department) => [$department->id => $this->deletion->analyze($department)]);

        return view('pages.departments.index', compact('departments', 'search', 'deletionAnalyses'));
    }

    public function create(): View
    {
        return view('pages.departments.create');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());

        return redirect()
            ->route('departments.show', $department)
            ->with('status', 'Department created successfully.');
    }

    public function show(Department $department): View
    {
        $department->load('programs');

        return view('pages.departments.show', compact('department'));
    }

    public function edit(Department $department): View
    {
        return view('pages.departments.edit', compact('department'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()
            ->route('departments.show', $department)
            ->with('status', 'Department updated successfully.');
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        $analysis = $this->deletion->analyze($department);

        if (! $this->deletion->delete($department)) {
            $this->auditLogger->log(
                $request->user(),
                'delete_blocked',
                'departments',
                Department::class,
                $department->id,
                [
                    'name' => $department->name,
                    'code' => $department->code,
                    'role' => $request->user()?->getRoleNames()->first(),
                    'succeeded' => false,
                    'reason' => $analysis->blockedMessage(),
                    'blockers' => $analysis->blockers,
                ],
            );

            return redirect()
                ->route('departments.index')
                ->with('error', $analysis->blockedMessage());
        }

        $this->auditLogger->log(
            $request->user(),
            'delete',
            'departments',
            Department::class,
            $department->id,
            [
                'name' => $department->name,
                'code' => $department->code,
                'role' => $request->user()?->getRoleNames()->first(),
                'succeeded' => true,
            ],
        );

        return redirect()
            ->route('departments.index')
            ->with('status', 'Department deleted successfully.');
    }
}
