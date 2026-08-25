@props([
    'analysis',
    'action',
    'title' => null,
])

@php
    $title ??= 'Delete '.ucfirst($analysis->recordType).'?';
@endphp

<button
    type="button"
    {{ $attributes->merge(['class' => 'flex w-full items-center gap-2 px-3 py-2 text-start text-sm text-danger-ink hover:bg-brand-soft']) }}
    @click="$dispatch('delete-record-open', {
        title: @js($title),
        recordName: @js($analysis->recordName),
        recordDetail: @js($analysis->recordDetail),
        warning: @js($analysis->warningMessage),
        blocked: @js(! $analysis->canDelete),
        blockedMessage: @js(! $analysis->canDelete ? $analysis->blockedMessage() : ''),
        blockers: @js($analysis->blockers),
        action: @js($action),
        confirmLabel: @js($analysis->confirmLabel),
        method: 'DELETE',
    })"
>
    Delete
</button>
