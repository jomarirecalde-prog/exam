@props([
    'padding' => true,
])

<div {{ $attributes->merge(['class' => 'ui-card'.($padding ? ' ui-card-pad' : '')]) }}>
    {{ $slot }}
</div>
