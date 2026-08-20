@props([
    'name',
    'show' => false,
    'maxWidth' => 'md',
    'title' => null,
])

@php
    $maxWidthClass = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ][$maxWidth] ?? 'sm:max-w-md';
@endphp

<div
    x-data="{ show: @js($show) }"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-labelledby="modal-{{ $name }}-title" @endif
>
    <div
        x-show="show"
        x-transition.opacity.duration.150ms
        class="fixed inset-0 bg-navy-950/50"
        x-on:click="show = false"
    ></div>

    <div
        x-show="show"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="relative mb-6 overflow-hidden rounded-modal border border-line bg-surface shadow-pop sm:mx-auto sm:w-full {{ $maxWidthClass }}"
        {{ $attributes }}
    >
        {{ $slot }}
    </div>
</div>
