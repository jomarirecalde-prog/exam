<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$program->name" :subtitle="$program->code">
            <x-ui.button variant="secondary" :href="route('programs.edit', $program)" icon="pencil" wire:navigate>Edit</x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card>
                <h2 class="ui-section">Program</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">Code</dt>
                        <dd class="mt-1 font-medium">{{ $program->code }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Name</dt>
                        <dd class="mt-1 font-medium">{{ $program->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Department</dt>
                        <dd class="mt-1 font-medium">{{ $program->department?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Description</dt>
                        <dd class="mt-1 leading-6">{{ $program->description ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Status</dt>
                        <dd class="mt-1"><x-ui.badge :status="$program->is_active ? 'active' : 'closed'" /></dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="ui-section">Year Levels</h2>
                @if ($program->yearLevels->isEmpty())
                    <p class="mt-4 text-sm text-muted">No year levels are configured for this program yet.</p>
                @else
                    <ul class="mt-4 divide-y divide-line">
                        @foreach ($program->yearLevels as $yearLevel)
                            <li class="flex items-center justify-between py-3 text-sm">
                                <span class="font-medium">{{ $yearLevel->name }}</span>
                                <x-ui.badge :status="$yearLevel->is_active ? 'active' : 'closed'" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
