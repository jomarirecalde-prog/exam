<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Subjects" subtitle="Courses that examinations can be created for.">
            <x-ui.button :href="route('subjects.create')" icon="plus" wire:navigate>Create Subject</x-ui.button>
        </x-ui.page-header>

        <form method="get" action="{{ route('subjects.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label class="relative block w-full max-w-sm">
                <span class="sr-only">Search subjects</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-faint">
                    <x-icon name="search" :size="16" />
                </span>
                <input type="search" name="q" value="{{ $search }}" class="ui-input pl-9" placeholder="Search subjects">
            </label>
            <x-ui.button variant="secondary" type="submit" size="sm">Search</x-ui.button>
        </form>

        <div class="ui-table-wrap mt-4">
            @if ($subjects->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state
                        title="No subjects yet."
                        icon="book-open"
                        action="Create Subject"
                        :action-href="route('subjects.create')"
                    >
                        Add a subject before creating examinations and question banks.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Subject</th>
                                <th>Department</th>
                                <th>Units</th>
                                <th>Status</th>
                                <th class="w-40"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subjects as $subject)
                                <tr>
                                    <td class="font-medium">{{ $subject->code }}</td>
                                    <td>
                                        <a href="{{ route('subjects.show', $subject) }}" class="font-medium hover:underline" wire:navigate>
                                            {{ $subject->name }}
                                        </a>
                                    </td>
                                    <td class="text-muted">{{ $subject->department?->code ?: '—' }}</td>
                                    <td class="text-muted">{{ $subject->units }}</td>
                                    <td>
                                        <x-ui.badge :status="$subject->is_active ? 'active' : 'closed'" />
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <a href="{{ route('subjects.show', $subject) }}" class="btn-ghost btn-sm" wire:navigate>View</a>
                                            <a href="{{ route('subjects.edit', $subject) }}" class="btn-ghost btn-sm" wire:navigate>Edit</a>
                                            <x-dropdown align="right" width="w-44">
                                                <x-slot name="trigger">
                                                    <button type="button" class="btn-icon h-8 w-8" aria-label="More actions">
                                                        <x-icon name="more-horizontal" :size="16" />
                                                    </button>
                                                </x-slot>
                                                <x-slot name="content">
                                                    <form method="post" action="{{ route('subjects.destroy', $subject) }}" onsubmit="return confirm('Deactivate this subject? It will no longer be available for new examinations.')">
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
                    {{ $subjects->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
