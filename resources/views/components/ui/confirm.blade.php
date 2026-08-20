@props(['title' => null, 'confirm' => 'Confirm', 'cancel' => 'Cancel', 'variant' => 'primary', 'name' => 'confirm'])

<x-modal :name="$name" maxWidth="sm">
    <div class="p-6">
        @if ($title)
            <h2 class="text-lg font-semibold text-ink">{{ $title }}</h2>
        @endif
        <p class="mt-2 text-sm leading-6 text-muted">{{ $slot }}</p>
        <div class="mt-6 flex justify-end gap-2">
            <x-ui.button variant="secondary" x-on:click="$dispatch('close')">{{ $cancel }}</x-ui.button>
            {{ $action ?? '' }}
        </div>
    </div>
</x-modal>
