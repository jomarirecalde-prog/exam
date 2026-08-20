<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Profile" subtitle="Your account details and password." />

        <div class="space-y-6">
            <x-ui.card>
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </x-ui.card>

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
