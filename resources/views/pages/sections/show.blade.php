<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$section->name" subtitle="Section details">
            <x-ui.button variant="secondary" :href="route('sections.edit', $section)" icon="pencil" wire:navigate>Edit</x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card>
                <h2 class="ui-section">Section</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">Name</dt>
                        <dd class="mt-1 font-medium">{{ $section->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Code</dt>
                        <dd class="mt-1">{{ $section->code ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Status</dt>
                        <dd class="mt-1"><x-ui.badge :status="$section->is_active ? 'active' : 'closed'" /></dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="ui-section">Placement</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">Program</dt>
                        <dd class="mt-1 font-medium">{{ $section->program?->code }} — {{ $section->program?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Year level</dt>
                        <dd class="mt-1">{{ $section->yearLevel?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Academic year</dt>
                        <dd class="mt-1">{{ $section->academicYear?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Semester</dt>
                        <dd class="mt-1">{{ $section->semester?->name ?: '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
