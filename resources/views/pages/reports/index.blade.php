<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Reports" subtitle="High-level examination summaries." />
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat label="Examinations" :value="number_format($counts['exams'])" />
            <x-ui.stat label="Students" :value="number_format($counts['students'])" />
            <x-ui.stat label="Released Results" :value="number_format($counts['released'])" />
        </div>
        <x-ui.card class="mt-8">
            <x-ui.empty-state title="Detailed reports will appear here." icon="file-text">
                Exportable class and item analysis reports can be added without changing this layout.
            </x-ui.empty-state>
        </x-ui.card>
    </div>
</x-app-layout>
