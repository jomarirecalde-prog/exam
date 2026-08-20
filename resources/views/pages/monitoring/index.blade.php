<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Monitoring" subtitle="Live examination sessions." />
        @if ($active->isEmpty())
            <x-ui.card>
                <x-ui.empty-state title="No active examinations." icon="activity">
                    When an examination is in progress, attempts and submissions will appear here.
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="grid gap-4">
                @foreach ($active as $exam)
                    <article class="ui-card ui-card-pad flex items-center justify-between">
                        <div>
                            <p class="font-semibold">{{ $exam->title }}</p>
                            <p class="mt-1 text-sm text-muted">{{ $exam->subject?->code }} · {{ $exam->sections->pluck('name')->filter()->join(', ') ?: 'Unassigned' }}</p>
                        </div>
                        <x-ui.badge status="active" />
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
