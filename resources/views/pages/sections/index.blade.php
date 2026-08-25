<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Sections" subtitle="Class sections used to assign students and examinations.">
            <x-ui.button :href="route('sections.create')" icon="plus" wire:navigate>Create Section</x-ui.button>
        </x-ui.page-header>

        <form method="get" action="{{ route('sections.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label class="relative block w-full max-w-sm">
                <span class="sr-only">Search sections</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-faint">
                    <x-icon name="search" :size="16" />
                </span>
                <input type="search" name="q" value="{{ $search }}" class="ui-input pl-9" placeholder="Search sections">
            </label>
            <x-ui.button variant="secondary" type="submit" size="sm">Search</x-ui.button>
        </form>

        <div class="ui-table-wrap mt-4">
            @if ($sections->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state
                        title="No sections yet."
                        icon="layers"
                        action="Create Section"
                        :action-href="route('sections.create')"
                    >
                        Create a section so students can be grouped for examinations.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Program</th>
                                <th>Year level</th>
                                <th>Status</th>
                                <th class="w-40"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sections as $section)
                                @php($analysis = $deletionAnalyses[$section->id] ?? null)
                                <tr>
                                    <td class="font-medium">
                                        <a href="{{ route('sections.show', $section) }}" class="hover:underline" wire:navigate>
                                            {{ $section->name }}
                                        </a>
                                        @if ($section->code)
                                            <p class="mt-0.5 text-sm font-normal text-muted">{{ $section->code }}</p>
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $section->program?->code ?: '—' }}</td>
                                    <td class="text-muted">{{ $section->yearLevel?->name ?: '—' }}</td>
                                    <td>
                                        <x-ui.badge :status="$section->is_active ? 'active' : 'closed'" />
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <a href="{{ route('sections.show', $section) }}" class="btn-ghost btn-sm" wire:navigate>View</a>
                                            <a href="{{ route('sections.edit', $section) }}" class="btn-ghost btn-sm" wire:navigate>Edit</a>
                                            <x-dropdown align="right" width="w-44">
                                                <x-slot name="trigger">
                                                    <button type="button" class="btn-icon h-8 w-8" aria-label="More actions">
                                                        <x-icon name="more-horizontal" :size="16" />
                                                    </button>
                                                </x-slot>
                                                <x-slot name="content">
                                                    @if ($analysis)
                                                        <x-ui.delete-record-trigger
                                                            :analysis="$analysis"
                                                            :action="route('sections.destroy', $section)"
                                                            title="Delete Section?"
                                                        />
                                                    @endif
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
                    {{ $sections->links() }}
                </div>
            @endif
        </div>

        <x-ui.delete-record-modal />
    </div>
</x-app-layout>
