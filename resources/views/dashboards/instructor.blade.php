<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$greeting" subtitle="Review your classes, create examinations, and monitor attempts.">
            <x-ui.button :href="route('examinations.create')" icon="plus">Create Examination</x-ui.button>
        </x-ui.page-header>

        @include('dashboards.partials.instructor-overview')
    </div>
</x-app-layout>
