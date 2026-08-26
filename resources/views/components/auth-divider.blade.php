<div {{ $attributes->merge(['class' => 'relative my-6']) }}>
    <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="w-full border-t border-line"></div>
    </div>
    <div class="relative flex justify-center text-xs uppercase tracking-wide">
        <span class="bg-surface px-3 text-muted">{{ $slot->isEmpty() ? 'or' : $slot }}</span>
    </div>
</div>
