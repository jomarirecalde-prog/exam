<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header
            title="Examination Monitoring"
            subtitle="Monitor live examinations or reopen ended ones to reactivate locked student attempts."
        />

        @if ($live->isEmpty() && $ended->isEmpty())
            <x-ui.card>
                <x-ui.empty-state title="No examinations to monitor" icon="activity">
                    Published examinations assigned to you will appear here.
                </x-ui.empty-state>
            </x-ui.card>
        @else
            @if ($live->isNotEmpty())
                <section>
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Live Examinations</h2>
                    <x-ui.card class="overflow-hidden" :padding="false">
                        <div class="divide-y divide-line">
                            @foreach ($live as $exam)
                                @include('pages.monitoring._exam-row', ['exam' => $exam, 'buttonLabel' => 'Monitor Examination'])
                            @endforeach
                        </div>
                    </x-ui.card>
                </section>
            @endif

            @if ($ended->isNotEmpty())
                <section @class(['mt-10' => $live->isNotEmpty()])>
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">Ended Examinations</h2>
                    <p class="mb-4 text-sm text-muted">
                        These examinations are no longer live, but you can still open them to review progress and reactivate locked students.
                    </p>
                    <x-ui.card class="overflow-hidden" :padding="false">
                        <div class="divide-y divide-line">
                            @foreach ($ended as $exam)
                                @include('pages.monitoring._exam-row', ['exam' => $exam, 'buttonLabel' => 'Open Monitoring'])
                            @endforeach
                        </div>
                    </x-ui.card>
                </section>
            @endif
        @endif
    </div>
</x-app-layout>
