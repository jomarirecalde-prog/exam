<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Profile" subtitle="Your account details and password." />

        <div class="space-y-6">
            <x-ui.card>
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </x-ui.card>

            @if(auth()->user()->hasRole('student'))
                <x-ui.card>
                    <div class="max-w-xl space-y-6">
                        <div>
                            <h2 class="text-lg font-semibold">Linked Accounts</h2>
                            <p class="mt-1 text-sm text-muted">Connect or disconnect your Google account for sign-in.</p>
                        </div>

                        @php
                            $googleAccount = auth()->user()->linkedAccounts()->where('provider', 'google')->first();
                            $canDisconnect = app(\App\Services\Google\LinkedAccountService::class)->canDisconnectGoogle(auth()->user());
                            $googleSettings = app(\App\Services\Google\GoogleIntegrationSettings::class);
                        @endphp

                        <div class="rounded-lg border border-line p-4">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-wide text-muted">Google Account</p>
                                    @if($googleAccount)
                                        <p class="mt-2 font-medium">{{ $googleAccount->provider_email }}</p>
                                        <p class="mt-1 text-sm text-success-ink">✓ Connected</p>
                                    @else
                                        <p class="mt-2 text-sm text-muted">Not Connected</p>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if(!$googleAccount && $googleSettings->isConfigured() && ($googleSettings->signInEnabled() || $googleSettings->registrationEnabled()))
                                        <a href="{{ route('account.google.connect') }}" class="btn-secondary">Connect Google Account</a>
                                    @elseif($googleAccount)
                                        @if($canDisconnect)
                                            <form method="post" action="{{ route('account.google.disconnect') }}" onsubmit="return confirm('Disconnect your Google account? You will still be able to sign in with your password.');">
                                                @csrf
                                                <input type="hidden" name="confirm" value="1">
                                                <button type="submit" class="btn-secondary text-danger-ink">Disconnect</button>
                                            </form>
                                        @else
                                            <p class="text-sm text-muted">Set a password before disconnecting Google.</p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($googleSettings->classroomEnabled())
                            <div class="border-t border-line pt-4">
                                <h3 class="text-base font-semibold">Google Classroom</h3>
                                <p class="mt-1 text-sm text-muted">Optionally connect Google Classroom to help match your classes with subject offerings.</p>
                                <a href="{{ route('google-classroom.index') }}" class="btn-secondary mt-4 inline-flex">Manage Google Classroom</a>
                            </div>
                        @endif
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card id="password">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </x-ui.card>

            <x-ui.card>
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
