@props(['search' => true, 'placeholder' => 'Search'])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between']) }}>
    @if ($search)
        <label class="relative block w-full max-w-sm">
            <span class="sr-only">{{ $placeholder }}</span>
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-faint">
                <x-icon name="search" :size="16" />
            </span>
            <input type="search" class="ui-input pl-9" placeholder="{{ $placeholder }}" {{ $attributes->except('class') }}>
        </label>
    @else
        <div></div>
    @endif
    <div class="flex flex-wrap items-center gap-2">
        {{ $slot }}
    </div>
</div>
