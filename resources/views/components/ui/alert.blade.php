@props([
    'type' => 'info',
    'title' => null,
])

@php
    $styles = [
        'success' => ['class' => 'border-success bg-success-soft text-success-ink', 'icon' => 'check-circle'],
        'warning' => ['class' => 'border-warning bg-warning-soft text-warning-ink', 'icon' => 'alert-triangle'],
        'error' => ['class' => 'border-danger bg-danger-soft text-danger-ink', 'icon' => 'x-circle'],
        'danger' => ['class' => 'border-danger bg-danger-soft text-danger-ink', 'icon' => 'x-circle'],
        'info' => ['class' => 'border-info bg-info-soft text-info-ink', 'icon' => 'info'],
    ][$type] ?? ['class' => 'border-line bg-brand-soft text-ink', 'icon' => 'info'];
@endphp

<div role="alert" {{ $attributes->merge(['class' => 'flex gap-3 rounded-card border px-4 py-3 text-sm '.$styles['class']]) }}>
    <x-icon :name="$styles['icon']" :size="18" class="mt-0.5" />
    <div>
        @if ($title)
            <p class="font-medium">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-0.5 text-current/80' : '' }}">{{ $slot }}</div>
    </div>
</div>
