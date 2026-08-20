@php
    use App\Support\Navigation;

    $user = auth()->user();
    $nav = Navigation::items($user);
    $isGrouped = isset($nav[0]['items']);
    $groups = $isGrouped ? $nav : [['label' => null, 'items' => $nav]];
    $institution = config('examination.institution.name');
@endphp

<aside
    class="fixed inset-y-0 left-0 z-40 flex flex-col border-r border-white/10 bg-[var(--ui-sidebar)] text-[var(--ui-sidebar-text)] transition-[width,transform] duration-ui"
    :class="{
        'sidebar-w': !collapsed,
        'sidebar-w-collapsed': collapsed,
        '-translate-x-full lg:translate-x-0': !mobileOpen
    }"
    aria-label="Primary"
>
    <div class="flex h-topbar items-center gap-3 border-b border-white/10 px-4">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3 text-white" wire:navigate>
            <x-application-logo class="h-8 w-8 shrink-0 text-white" />
            <span class="truncate text-sm font-semibold tracking-tight" x-show="!collapsed" x-cloak>{{ $institution }}</span>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 py-4">
        @foreach ($groups as $group)
            @if ($group['label'])
                <p class="mb-2 mt-5 px-3 text-[11px] font-medium uppercase tracking-[0.14em] text-white/35" x-show="!collapsed" x-cloak>{{ $group['label'] }}</p>
            @endif
            <ul class="space-y-0.5">
                @foreach ($group['items'] as $item)
                    @php $active = Navigation::isActive($item['route']); @endphp
                    <li>
                        <a
                            href="{{ route($item['route']) }}"
                            wire:navigate
                            @class([
                                'group relative flex items-center gap-3 rounded-btn px-3 py-2 text-sm transition duration-ui',
                                'bg-white/10 text-[var(--ui-sidebar-active)]' => $active,
                                'hover:bg-white/5 hover:text-white' => ! $active,
                            ])
                            :title="collapsed ? '{{ $item['label'] }}' : ''"
                            @if ($active) aria-current="page" @endif
                        >
                            <x-icon :name="$item['icon']" :size="18" class="shrink-0" />
                            <span class="truncate" x-show="!collapsed" x-cloak>{{ $item['label'] }}</span>
                            <span class="ui-tooltip lg:block hidden" x-show="collapsed" x-cloak>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>

    <div class="border-t border-white/10 p-3">
        <button
            type="button"
            class="hidden w-full items-center gap-3 rounded-btn px-3 py-2 text-sm text-white/70 hover:bg-white/5 hover:text-white lg:flex"
            @click="toggleCollapsed()"
            :aria-expanded="(!collapsed).toString()"
            aria-label="Collapse sidebar"
        >
            <x-icon name="panel-left" :size="18" />
            <span x-show="!collapsed" x-cloak>Collapse</span>
        </button>
    </div>
</aside>
