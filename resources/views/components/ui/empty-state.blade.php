@props([
    'icon' => 'clipboard-list',
    'title' => 'Nothing here yet',
    'action' => null,
    'actionHref' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-start gap-3 py-10']) }}>
    <div class="flex h-11 w-11 items-center justify-center rounded-card border border-line bg-brand-soft text-muted">
        <x-icon :name="$icon" :size="20" />
    </div>
    <div>
        <h3 class="text-base font-semibold text-ink">{{ $title }}</h3>
        <p class="ui-help max-w-md">{{ $slot }}</p>
    </div>
    @if ($action && $actionHref)
        <x-ui.button :href="$actionHref" icon="plus">{{ $action }}</x-ui.button>
    @endif
</div>
