<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header
            title="Google Integration Settings"
            subtitle="Configure Google Sign-In, registration, and optional Google Classroom integration."
        />

        @if (session('status'))
            <x-ui.alert type="success" class="mb-6">{{ session('status') }}</x-ui.alert>
        @endif

        <form method="post" action="{{ route('admin.google-integration.update') }}" class="max-w-3xl space-y-6">
            @csrf
            @method('PUT')

            <x-ui.card>
                <div class="space-y-5">
                    <h2 class="text-lg font-semibold">Google Integration Settings</h2>

                    @unless($settings['is_configured'])
                        <x-ui.alert type="warning" title="Credentials required">
                            Set <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> in your environment before enabling Google features.
                        </x-ui.alert>
                    @endunless

                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="sign_in_enabled" value="1" class="mt-1 rounded border-line" @checked(old('sign_in_enabled', $settings['sign_in_enabled']))>
                        <span>
                            <span class="block font-medium">Enable Google Sign-In</span>
                            <span class="block text-sm text-muted">Allow students to sign in with a linked Google account.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="registration_enabled" value="1" class="mt-1 rounded border-line" @checked(old('registration_enabled', $settings['registration_enabled']))>
                        <span>
                            <span class="block font-medium">Enable Google Registration</span>
                            <span class="block text-sm text-muted">Allow new students to start registration using Google, then complete required school details.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="classroom_enabled" value="1" class="mt-1 rounded border-line" @checked(old('classroom_enabled', $settings['classroom_enabled']))>
                        <span>
                            <span class="block font-medium">Enable Google Classroom Integration</span>
                            <span class="block text-sm text-muted">Optional helper for matching Classroom courses with subject offerings. Does not replace official enrollment.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="require_school_domain" value="1" class="mt-1 rounded border-line" @checked(old('require_school_domain', $settings['require_school_domain']))>
                        <span>
                            <span class="block font-medium">Require Approved School Google Domain</span>
                            <span class="block text-sm text-muted">Only allow Google accounts from your institution domain.</span>
                        </span>
                    </label>

                    <x-ui.field label="Allowed Domain" for="allowed_domain" help="Example: school.edu (without @)">
                        <input type="text" id="allowed_domain" name="allowed_domain" value="{{ old('allowed_domain', $settings['allowed_domain']) }}" class="ui-input" placeholder="school.edu">
                        @error('allowed_domain')
                            <p class="ui-error">{{ $message }}</p>
                        @enderror
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</x-app-layout>
