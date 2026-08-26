@php
    use App\Services\Google\GoogleIntegrationSettings;
    $googleSettings = app(GoogleIntegrationSettings::class);
    $googleEnabled = $googleSettings->isConfigured() && ($googleSettings->signInEnabled() || $googleSettings->registrationEnabled());
@endphp

@if ($googleEnabled)
    <div
        x-data="{ online: navigator.onLine }"
        x-init="window.addEventListener('online', () => online = true); window.addEventListener('offline', () => online = false)"
        class="mt-6"
    >
        <x-google-button
            :href="route('auth.google', ['intent' => $intent ?? 'login'])"
            :label="$label ?? 'Continue with Google'"
            :disabled="false"
            x-bind:class="!online ? 'pointer-events-none opacity-50' : ''"
        />

        <x-ui.alert type="warning" title="Internet connection required" class="mt-4" x-show="!online" x-cloak>
            Google Sign-In requires an internet connection. Please reconnect and try again.
        </x-ui.alert>
    </div>

    <x-auth-divider />
@endif

@if (session('google_error'))
    <x-ui.alert type="error" title="Google Sign-In" class="mt-4">
        {{ session('google_error') }}
    </x-ui.alert>
@endif

@error('google')
    <x-ui.alert type="error" title="Google Sign-In" class="mt-4">
        {{ $message }}
    </x-ui.alert>
@enderror
