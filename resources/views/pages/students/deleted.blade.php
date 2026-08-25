<x-app-layout>
    <div class="ui-page" x-data="{ restoreOpen: false, restoreAction: '', restoreName: '', restoreDetail: '', restoreSubmitting: false }">
        <x-ui.page-header title="Deleted Students" subtitle="Review and restore soft-deleted student accounts.">
            <x-ui.button :href="route('students.index')" variant="secondary" wire:navigate>Back to Students</x-ui.button>
        </x-ui.page-header>

        <form method="get" action="{{ route('students.deleted.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label class="relative block w-full max-w-sm">
                <span class="sr-only">Search deleted students</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-faint">
                    <x-icon name="search" :size="16" />
                </span>
                <input type="search" name="q" value="{{ $search }}" class="ui-input pl-9" placeholder="Search deleted students">
            </label>
            <x-ui.button variant="secondary" type="submit" size="sm">Search</x-ui.button>
        </form>

        <div class="ui-table-wrap mt-4">
            @if ($students->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="No deleted students." icon="graduation-cap">
                        Soft-deleted student accounts will appear here for recovery.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Deleted</th>
                                <th>Deleted By</th>
                                <th class="w-44"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                <tr>
                                    <td class="font-medium">
                                        {{ $student->displayName() }}
                                        <p class="mt-0.5 text-sm font-normal text-muted">{{ $student->user?->email }}</p>
                                    </td>
                                    <td class="text-muted">{{ $student->student_id }}</td>
                                    <td class="text-muted">{{ $student->deleted_at?->timezone(config('examination.timezone', 'Asia/Manila'))->format('M j, Y g:i A') }}</td>
                                    <td class="text-muted">{{ $student->deletedBy?->fullName() ?: $student->deletedBy?->name ?: '—' }}</td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <button
                                                type="button"
                                                class="btn-ghost btn-sm"
                                                @click="restoreOpen = true; restoreAction = @js(route('students.restore', $student->id)); restoreName = @js($student->displayName()); restoreDetail = @js('Student ID: '.$student->student_id); restoreSubmitting = false"
                                            >
                                                Restore
                                            </button>
                                            <x-dropdown align="right" width="w-52">
                                                <x-slot name="trigger">
                                                    <button type="button" class="btn-icon h-8 w-8" aria-label="More actions">
                                                        <x-icon name="more-horizontal" :size="16" />
                                                    </button>
                                                </x-slot>
                                                <x-slot name="content">
                                                    @php
                                                        $forceAnalysis = new \App\Support\DeletionAnalysis(
                                                            canDelete: true,
                                                            recordType: 'student',
                                                            recordName: $student->displayName(),
                                                            recordDetail: 'Student ID: '.$student->student_id,
                                                            warningMessage: 'This will permanently remove the student account. If examination history exists, permanent deletion will be blocked to preserve records.',
                                                            confirmLabel: 'Permanently Delete',
                                                        );
                                                    @endphp
                                                    <x-ui.delete-record-trigger
                                                        :analysis="$forceAnalysis"
                                                        :action="route('students.force-destroy', $student->id)"
                                                        title="Permanently Delete Student?"
                                                    />
                                                </x-slot>
                                            </x-dropdown>
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

        <div x-show="restoreOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" @keydown.escape.window="restoreOpen = false">
            <div class="ui-card ui-card-pad w-full max-w-md" @click.outside="restoreOpen = false">
                <h2 class="text-lg font-semibold text-ink">Restore Student?</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Are you sure you want to restore this student account?</p>
                <div class="mt-4 rounded-card border border-line bg-canvas px-4 py-3">
                    <p class="font-medium text-ink" x-text="restoreName"></p>
                    <p class="mt-0.5 text-sm text-muted" x-text="restoreDetail"></p>
                </div>
                <form
                    method="post"
                    :action="restoreAction"
                    class="mt-6 flex justify-end gap-2"
                    @submit="restoreSubmitting = true"
                >
                    @csrf
                    <x-ui.button variant="secondary" type="button" @click="restoreOpen = false" ::disabled="restoreSubmitting">Cancel</x-ui.button>
                    <x-ui.button type="submit" ::disabled="restoreSubmitting">
                        <span x-show="!restoreSubmitting">Restore Student</span>
                        <span x-show="restoreSubmitting">Restoring...</span>
                    </x-ui.button>
                </form>
            </div>
        </div>

        <x-ui.delete-record-modal />
    </div>
</x-app-layout>
