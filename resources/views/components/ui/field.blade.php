@props(['label' => null, 'for' => null, 'help' => null, 'error' => null])

<div {{ $attributes->merge(['class' => 'space-y-0']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="ui-label">{{ $label }}</label>
    @endif
    {{ $slot }}
    @if ($help && ! $error)
        <p class="ui-help">{{ $help }}</p>
    @endif
    @if ($error)
        <p class="ui-error" role="alert">{{ is_array($error) ? $error[0] : $error }}</p>
    @endif
</div>
