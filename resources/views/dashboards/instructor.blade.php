<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$greeting" subtitle="Create examinations, grade submissions, and monitor attempts.">
            <x-ui.button :href="route('examinations.create')" icon="plus">Create Examination</x-ui.button>
        </x-ui.page-header>

        @include('dashboards.partials.staff-overview')
    </div>
</x-app-layout>
