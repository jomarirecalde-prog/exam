<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Students" subtitle="Manage enrolled students and their academic placement." />

        <form method="get" action="{{ route('students.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label class="relative block w-full max-w-sm">
                <span class="sr-only">Search students</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-faint">
                    <x-icon name="search" :size="16" />
                </span>
                <input type="search" name="q" value="{{ $search }}" class="ui-input pl-9" placeholder="Search students">
            </label>
            <x-ui.button variant="secondary" type="submit" size="sm">Search</x-ui.button>
        </form>

        <div class="ui-table-wrap mt-4">
            @if ($students->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="No students yet." icon="graduation-cap">
                        Approved student registrations will appear here once they are enrolled.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Program</th>
                                <th>Section</th>
                                <th>Status</th>
                                <th class="w-40"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                <tr>
                                    <td class="font-medium">
                                        <a href="{{ route('students.show', $student) }}" class="hover:underline" wire:navigate>
                                            {{ $student->user?->fullName() ?: $student->user?->name }}
                                        </a>
                                        <p class="mt-0.5 text-sm font-normal text-muted">{{ $student->user?->email }}</p>
                                    </td>
                                    <td class="text-muted">{{ $student->student_id }}</td>
                                    <td class="text-muted">{{ $student->program?->code ?: '—' }}</td>
                                    <td class="text-muted">{{ $student->section?->displayName() ?: '—' }}</td>
                                    <td>
                                        <x-ui.badge :status="$student->is_active ? 'active' : 'closed'" />
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('students.show', $student) }}" class="btn-ghost btn-sm" wire:navigate>View</a>
                                            <a href="{{ route('students.edit', $student) }}" class="btn-ghost btn-sm" wire:navigate>Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-4 py-3 text-sm text-muted">
                    {{ $students->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
