@props([
    'sections' => [],
    'limit' => 2,
])

@php
    $items = collect($sections);
    $visible = $items->take($limit);
    $hidden = $items->slice($limit);
@endphp

@if ($items->isEmpty())
    <span class="text-sm text-warning-ink">Needs review</span>
@else
    <div class="flex flex-wrap items-center gap-1.5">
        @foreach ($visible as $section)
            <span class="ui-chip">{{ $section->displayName() }}</span>
        @endforeach
        @if ($hidden->isNotEmpty())
            <div class="relative" x-data="{ open: false }">
                <button
                    type="button"
                    class="ui-chip ui-chip-more"
                    @click="open = !open"
                    @keydown.escape.window="open = false"
                    @click.outside="open = false"
                    aria-expanded="false"
                    x-bind:aria-expanded="open.toString()"
                >
                    +{{ $hidden->count() }}
                </button>
                <div
                    x-show="open"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    class="absolute left-0 top-full z-20 mt-2 min-w-[10rem] rounded-card border border-line bg-surface p-2 shadow-pop"
                    role="tooltip"
                >
                    <p class="px-1 pb-1 text-[11px] font-medium uppercase tracking-wide text-faint">Also assigned</p>
                    <ul class="space-y-1">
                        @foreach ($hidden as $section)
                            <li class="rounded-md px-2 py-1 text-xs text-ink">{{ $section->displayName() }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
@endif
