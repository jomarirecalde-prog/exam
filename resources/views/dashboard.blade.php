<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$greeting" subtitle="You’re signed in." />
        @include('dashboards.partials.staff-overview')
    </div>
</x-app-layout>
