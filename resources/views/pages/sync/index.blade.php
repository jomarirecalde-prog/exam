<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Synchronization" subtitle="Move examination data between online and offline deployments.">
            <x-ui.button variant="secondary" icon="refresh-cw" onclick="window.appToast('Unable to synchronize examination.', 'error')">Synchronize Data</x-ui.button>
        </x-ui.page-header>

        <x-ui.alert :type="$online ? 'info' : 'warning'" class="max-w-xl">
            {{ $online ? 'This instance is running in online mode.' : 'This instance is running offline. Synchronization is used to publish results later.' }}
        </x-ui.alert>

        <div class="ui-table-wrap mt-6">
            @if ($queue->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="Nothing waiting to sync." icon="refresh-cw">
                        Queued records will appear here with a clear synced or pending status.
                    </x-ui.empty-state>
                </div>
            @else
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Entity</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($queue as $item)
                            <tr>
                                <td class="font-medium">{{ $item->entity_type }}</td>
                                <td><x-ui.badge :status="strtolower($item->status) === 'synced' ? 'synced' : 'pending'" /></td>
                                <td class="text-muted">{{ $item->updated_at }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
