<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
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

        return view('pages.departments.index', compact('departments', 'search'));
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

    public function destroy(Department $department): RedirectResponse
    {
        $department->update(['is_active' => false]);

        return redirect()
            ->route('departments.index')
            ->with('status', 'Department deactivated.');
    }
}
