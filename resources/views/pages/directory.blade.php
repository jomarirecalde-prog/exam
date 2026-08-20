<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$title" :subtitle="$subtitle" />

        <x-ui.toolbar placeholder="Search {{ strtolower($title) }}" />

        <div class="ui-table-wrap mt-4" wire:loading.class="opacity-60">
            @if ($rows->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state :title="$emptyTitle" :icon="$emptyIcon">
                        {{ $emptyBody }}
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                @foreach ($columns as $column)
                                    <th>{{ $column }}</th>
                                @endforeach
                                <th class="w-32"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach ($row as $index => $cell)
                                        <td @class(['font-medium' => $index === 0, 'text-muted' => $index > 0])>
                                            @if ($index === count($row) - 1 && in_array($cell, ['Active', 'Closed', 'Draft'], true))
                                                <x-ui.badge :status="$cell === 'Active' ? 'active' : 'closed'" />
                                            @else
                                                {{ $cell ?: '—' }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="btn-ghost btn-sm">View</button>
                                            <button type="button" class="btn-ghost btn-sm">Edit</button>
                                            <button type="button" class="btn-icon h-8 w-8" aria-label="More">
                                                <x-icon name="more-horizontal" :size="16" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-4 py-3 text-sm text-muted">
                    {{ $rows->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
