@props([
    'href',
    'label' => 'Continue with Google',
    'disabled' => false,
])

<a
    href="{{ $href }}"
    @class([
        'inline-flex w-full items-center justify-center gap-3 rounded-lg border border-line bg-surface px-4 py-2.5 text-sm font-medium text-ink shadow-sm transition hover:bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2',
        'pointer-events-none opacity-50' => $disabled,
    ])
    @if($disabled) aria-disabled="true" tabindex="-1" @endif
>
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
        <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303C33.654 32.657 29.083 36 24 36c-5.514 0-10-4.486-10-10s4.486-10 10-10c2.484 0 4.735.908 6.494 2.411l5.657-5.657C33.64 10.053 29.082 8 24 8 14.059 8 6 16.059 6 26s8.059 18 18 18 18-8.059 18-18c0-1.089-.099-2.152-.389-3.917z"/>
        <path fill="#FF3D00" d="M6 26c0-1.657.285-3.245.796-4.735l9.053 7.023C14.708 30.614 19.031 34 24 34c2.484 0 4.735-.908 6.494-2.411l5.657 5.657C33.64 37.947 29.082 40 24 40 14.059 40 6 31.941 6 22v4z"/>
        <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A9.865 9.865 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-9.053 7.023C6.714 39.613 14.709 44 24 44z"/>
        <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.651-.389-3.917z"/>
    </svg>
    <span>{{ $label }}</span>
</a>
