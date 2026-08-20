<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$department->name" subtitle="Department details">
            <x-ui.button variant="secondary" :href="route('departments.edit', $department)" icon="pencil" wire:navigate>Edit</x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card>
                <h2 class="ui-section">Department</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">Code</dt>
                        <dd class="mt-1 font-medium">{{ $department->code }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Name</dt>
                        <dd class="mt-1 font-medium">{{ $department->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Description</dt>
                        <dd class="mt-1 leading-6">{{ $department->description ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Status</dt>
                        <dd class="mt-1"><x-ui.badge :status="$department->is_active ? 'active' : 'closed'" /></dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="ui-section">Programs</h2>
                @if ($department->programs->isEmpty())
                    <p class="mt-4 text-sm text-muted">No programs are assigned to this department yet.</p>
                @else
                    <ul class="mt-4 divide-y divide-line">
                        @foreach ($department->programs as $program)
                            <li class="flex items-center justify-between py-3 text-sm">
                                <span class="font-medium">{{ $program->code }}</span>
                                <span class="text-muted">{{ $program->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
