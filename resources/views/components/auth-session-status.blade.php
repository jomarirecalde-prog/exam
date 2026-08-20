@props(['status'])

@if ($status)
    <x-ui.alert type="success" class="mb-4">{{ $status }}</x-ui.alert>
@endif
