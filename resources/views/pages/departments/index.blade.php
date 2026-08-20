<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Departments" subtitle="Academic departments in your institution.">
            <x-ui.button :href="route('departments.create')" icon="plus" wire:navigate>Create Department</x-ui.button>
        </x-ui.page-header>

        <form method="get" action="{{ route('departments.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label class="relative block w-full max-w-sm">
                <span class="sr-only">Search departments</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-faint">
                    <x-icon name="search" :size="16" />
                </span>
                <input type="search" name="q" value="{{ $search }}" class="ui-input pl-9" placeholder="Search departments">
            </label>
            <x-ui.button variant="secondary" type="submit" size="sm">Search</x-ui.button>
        </form>

        <div class="ui-table-wrap mt-4">
            @if ($departments->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state
                        title="No departments yet."
                        icon="building-2"
                        action="Create Department"
                        :action-href="route('departments.create')"
                    >
                        Create a department to organize programs and faculty.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Department</th>
                                <th>Programs</th>
                                <th>Status</th>
                                <th class="w-40"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($departments as $department)
                                <tr>
                                    <td class="font-medium">{{ $department->code }}</td>
                                    <td>
                                        <a href="{{ route('departments.show', $department) }}" class="font-medium hover:underline" wire:navigate>
                                            {{ $department->name }}
                                        </a>
                                    </td>
                                    <td class="text-muted">{{ $department->programs_count }}</td>
                                    <td>
                                        <x-ui.badge :status="$department->is_active ? 'active' : 'closed'" />
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <a href="{{ route('departments.show', $department) }}" class="btn-ghost btn-sm" wire:navigate>View</a>
                                            <a href="{{ route('departments.edit', $department) }}" class="btn-ghost btn-sm" wire:navigate>Edit</a>
                                            <x-dropdown align="right" width="w-44">
                                                <x-slot name="trigger">
                                                    <button type="button" class="btn-icon h-8 w-8" aria-label="More actions">
                                                        <x-icon name="more-horizontal" :size="16" />
                                                    </button>
                                                </x-slot>
                                                <x-slot name="content">
                                                    <form method="post" action="{{ route('departments.destroy', $department) }}" onsubmit="return confirm('Deactivate this department?')">
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
                    {{ $departments->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
