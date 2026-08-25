<?php

namespace App\Http\Controllers;

use App\Enums\StudentSubjectChangeRequestStatus;
use App\Models\StudentSubjectChangeRequest;
use App\Services\Students\StudentSubjectEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStudentSubjectRequestController extends Controller
{
    public function __construct(
        protected StudentSubjectEnrollmentService $subjectEnrollments,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
        ]);

        $requests = StudentSubjectChangeRequest::query()
            ->with(['student.user', 'student.program', 'student.section', 'academicYear', 'semester'])
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('pages.admin.student-subject-requests.index', [
            'requests' => $requests,
            'filters' => $filters,
        ]);
    }

    public function show(StudentSubjectChangeRequest $changeRequest): View
    {
        $changeRequest->load([
            'student.user',
            'student.program.department',
            'student.section',
            'academicYear',
            'semester',
            'reviewer',
        ]);

        $subjects = \App\Models\Subject::query()
            ->whereIn('id', collect($changeRequest->add_subject_ids ?? [])
                ->merge($changeRequest->remove_subject_ids ?? [])
                ->unique())
            ->get()
            ->keyBy('id');

        return view('pages.admin.student-subject-requests.show', [
            'changeRequest' => $changeRequest,
            'subjects' => $subjects,
        ]);
    }

    public function approve(Request $request, StudentSubjectChangeRequest $changeRequest): RedirectResponse
    {
        abort_unless($changeRequest->status === StudentSubjectChangeRequestStatus::Pending, 422);

        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->subjectEnrollments->approveChangeRequest($changeRequest, $request->user(), $data['admin_notes'] ?? null);

        return redirect()
            ->route('admin.student-subject-requests.show', $changeRequest)
            ->with('status', 'Subject change request approved.');
    }

    public function reject(Request $request, StudentSubjectChangeRequest $changeRequest): RedirectResponse
    {
        abort_unless($changeRequest->status === StudentSubjectChangeRequestStatus::Pending, 422);

        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->subjectEnrollments->rejectChangeRequest($changeRequest, $request->user(), $data['admin_notes'] ?? null);

        return redirect()
            ->route('admin.student-subject-requests.show', $changeRequest)
            ->with('status', 'Subject change request rejected.');
    }
}
