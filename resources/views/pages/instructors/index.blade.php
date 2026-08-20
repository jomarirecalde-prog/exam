<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Instructors" subtitle="Faculty members who create and grade examinations.">
            <x-ui.button :href="route('instructors.create')" icon="plus" wire:navigate>Add Instructor</x-ui.button>
        </x-ui.page-header>

        <form method="get" action="{{ route('instructors.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label class="relative block w-full max-w-sm">
                <span class="sr-only">Search instructors</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-faint">
                    <x-icon name="search" :size="16" />
                </span>
                <input type="search" name="q" value="{{ $search }}" class="ui-input pl-9" placeholder="Search instructors">
            </label>
            <x-ui.button variant="secondary" type="submit" size="sm">Search</x-ui.button>
        </form>

        <div class="ui-table-wrap mt-4">
            @if ($instructors->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state
                        title="No instructors yet."
                        icon="users"
                        action="Add Instructor"
                        :action-href="route('instructors.create')"
                    >
                        Add an instructor to assign subjects and examinations.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Instructor</th>
                                <th>Employee ID</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th class="w-40"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($instructors as $instructor)
                                <tr>
                                    <td class="font-medium">
                                        <a href="{{ route('instructors.show', $instructor) }}" class="hover:underline" wire:navigate>
                                            {{ $instructor->user?->fullName() ?: $instructor->user?->name }}
                                        </a>
                                        <p class="mt-0.5 text-sm font-normal text-muted">{{ $instructor->user?->email }}</p>
                                    </td>
                                    <td class="text-muted">{{ $instructor->employee_id }}</td>
                                    <td class="text-muted">{{ $instructor->department?->name ?: '—' }}</td>
                                    <td>
                                        <x-ui.badge :status="$instructor->is_active ? 'active' : 'closed'" />
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <a href="{{ route('instructors.show', $instructor) }}" class="btn-ghost btn-sm" wire:navigate>View</a>
                                            <a href="{{ route('instructors.edit', $instructor) }}" class="btn-ghost btn-sm" wire:navigate>Edit</a>
                                            <x-dropdown align="right" width="w-44">
                                                <x-slot name="trigger">
                                                    <button type="button" class="btn-icon h-8 w-8" aria-label="More actions">
                                                        <x-icon name="more-horizontal" :size="16" />
                                                    </button>
                                                </x-slot>
                                                <x-slot name="content">
                                                    <form method="post" action="{{ route('instructors.destroy', $instructor) }}" onsubmit="return confirm('Deactivate this instructor? They will no longer be able to sign in.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-start text-sm text-danger-ink hover:bg-brand-soft">
                                                            Deactivate
                                                        </button>
                                                    </form>
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
                    {{ $instructors->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
