<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Subject Change Requests" subtitle="Review student requests to modify declared subject enrollment." />

        <form method="get" class="mt-4 flex flex-wrap gap-3">
            <select name="status" class="ui-input w-auto">
                <option value="">All statuses</option>
                <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Approved</option>
                <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected</option>
            </select>
            <x-ui.button type="submit" variant="secondary" size="sm">Filter</x-ui.button>
        </form>

        <div class="ui-table-wrap mt-4">
            @if ($requests->isEmpty())
                <div class="px-5 py-8 text-sm text-muted">No subject change requests found.</div>
            @else
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Term</th>
                            <th>Changes</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $request)
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $request->student?->user?->fullName() ?: '—' }}</p>
                                    <p class="text-sm text-muted">{{ $request->student?->student_id }}</p>
                                </td>
                                <td class="text-muted">{{ $request->academicYear?->name }} · {{ $request->semester?->name }}</td>
                                <td class="text-sm text-muted">
                                    +{{ count($request->add_subject_ids ?? []) }} / −{{ count($request->remove_subject_ids ?? []) }}
                                </td>
                                <td><x-ui.badge :status="$request->status->value" /></td>
                                <td class="text-muted">{{ $request->created_at?->format('M j, Y') }}</td>
                                <td class="text-right">
                                    <a href="{{ route('admin.student-subject-requests.show', $request) }}" class="text-sm font-medium hover:underline" wire:navigate>Review</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-5 py-4">{{ $requests->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
