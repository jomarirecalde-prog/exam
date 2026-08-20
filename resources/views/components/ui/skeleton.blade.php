@props([
    'lines' => 1,
])

<div {{ $attributes->merge(['class' => 'space-y-3']) }} role="status" aria-label="Loading">
    @for ($i = 0; $i < $lines; $i++)
        <div class="skeleton h-4 w-full {{ $i === $lines - 1 ? 'max-w-[70%]' : '' }}"></div>
    @endfor
    <span class="sr-only">Loading</span>
</div>
