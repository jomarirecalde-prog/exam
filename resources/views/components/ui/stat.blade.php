@props([
    'label',
    'value' => '—',
    'hint' => null,
])

<div {{ $attributes->merge(['class' => 'ui-card ui-card-pad']) }}>
    <p class="text-sm text-muted">{{ $label }}</p>
    <p class="mt-2 text-2xl font-semibold tracking-tight text-ink">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-faint">{{ $hint }}</p>
    @endif
</div>
