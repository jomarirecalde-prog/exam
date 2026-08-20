<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Audit Logs" subtitle="A readable history of important platform actions." />
        <div class="ui-table-wrap">
            @if ($logs->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="No audit events yet." icon="scroll">
                        Sign-ins, publications, and grading actions will be listed here.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Module</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td class="font-medium">{{ $log->action }}</td>
                                    <td class="text-muted">{{ $log->module }}</td>
                                    <td class="text-muted">{{ $log->created_at }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-4 py-3">{{ $logs->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
