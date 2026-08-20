@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'size' => 'md',
    'icon' => null,
])

@php
    $classes = match ($variant) {
        'secondary' => 'btn-secondary',
        'ghost' => 'btn-ghost',
        'danger' => 'btn-danger',
        default => 'btn-primary',
    };

    if ($size === 'sm') {
        $classes .= ' btn-sm';
    }

    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="{{ $type }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}
>
    @if ($icon)
        <x-icon :name="$icon" :size="$size === 'sm' ? 16 : 18" />
    @endif
    {{ $slot }}
</{{ $tag }}>
