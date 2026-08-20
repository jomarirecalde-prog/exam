<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\UpdateSubjectRequest;
use App\Models\Department;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $subject = Subject::create($request->validated());

        return redirect()
            ->route('subjects.show', $subject)
            ->with('status', 'Subject created successfully.');
    }

    public function show(Subject $subject): View
    {
        $subject->load('department');

        return view('pages.subjects.show', compact('subject'));
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
        return [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
