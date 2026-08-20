@props(['items' => []])

<nav aria-label="Breadcrumb" class="hidden min-w-0 md:block">
    <ol class="flex items-center gap-2 text-sm text-muted">
        @foreach ($items as $index => $item)
            <li class="flex items-center gap-2">
                @if ($index > 0)
                    <x-icon name="chevron-right" :size="14" class="text-faint" />
                @endif
                @if (isset($item[1]))
                    <a href="{{ route($item[1]) }}" class="hover:text-ink" wire:navigate>{{ $item[0] }}</a>
                @else
                    <span class="truncate font-medium text-ink">{{ $item[0] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
