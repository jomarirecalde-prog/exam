<?php

use App\Livewire\Actions\Logout;
use App\Support\Navigation;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(url('/'), navigate: true);
    }
}; ?>

@php
    $user = auth()->user();
    $initials = collect(explode(' ', $user->fullName() ?: $user->name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
@endphp

<header class="sticky top-0 z-20 border-b border-line bg-surface/90 backdrop-blur">
    <div class="flex h-topbar items-center gap-3 px-4 sm:px-6">
        <button type="button" class="btn-icon lg:hidden" @click="mobileOpen = !mobileOpen" aria-label="Open navigation">
            <x-icon name="menu" :size="20" />
        </button>

        <button type="button" class="btn-icon hidden lg:inline-flex" @click="toggleCollapsed()" aria-label="Toggle sidebar">
            <x-icon name="panel-left" :size="18" />
        </button>

        <x-ui.breadcrumb :items="Navigation::breadcrumbs()" class="min-w-0 flex-1" />

        <div class="ml-auto flex items-center gap-1">
            <label class="relative hidden md:block">
                <span class="sr-only">Search</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-faint">
                    <x-icon name="search" :size="16" />
                </span>
                <input type="search" class="ui-input w-56 py-2 pl-9" placeholder="Search">
            </label>

            <x-dropdown align="right" width="w-72">
                <x-slot name="trigger">
                    <button type="button" class="btn-icon relative" aria-label="Notifications">
                        <x-icon name="bell" :size="18" />
                    </button>
                </x-slot>
                <x-slot name="content">
                    <div class="px-3 py-3">
                        <p class="text-sm font-medium text-ink">Notifications</p>
                        <p class="mt-2 text-sm text-muted">You’re all caught up.</p>
                    </div>
                </x-slot>
            </x-dropdown>

            <x-dropdown align="right" width="w-56">
                <x-slot name="trigger">
                    <button type="button" class="flex items-center gap-2 rounded-btn px-1.5 py-1 hover:bg-brand-soft" aria-label="User menu">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand text-xs font-semibold text-white dark:text-navy-950">
                            {{ $initials }}
                        </span>
                        <span class="hidden text-left text-sm lg:block">
                            <span class="block font-medium leading-tight" x-data="{{ json_encode(['name' => $user->fullName() ?: $user->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                            <span class="block text-xs text-muted">{{ $user->getRoleNames()->first() }}</span>
                        </span>
                        <x-icon name="chevron-down" :size="14" class="hidden text-faint lg:block" />
                    </button>
                </x-slot>
                <x-slot name="content">
                    <x-dropdown-link href="{{ route('profile') }}" wire:navigate>
                        <x-icon name="user" :size="16" /> {{ __('Profile') }}
                    </x-dropdown-link>
                    <x-dropdown-link href="{{ route('settings.index') }}" wire:navigate>
                        <x-icon name="settings" :size="16" /> {{ __('Settings') }}
                    </x-dropdown-link>
                    @if(auth()->user()?->hasRole('student'))
                        <x-dropdown-link href="{{ route('offline.sync') }}" wire:navigate>
                            <x-icon name="refresh-cw" :size="16" /> {{ __('Sync Status') }}
                        </x-dropdown-link>
                    @endif
                    <x-dropdown-link href="{{ route('profile') }}#password" wire:navigate>
                        <x-icon name="key" :size="16" /> {{ __('Change Password') }}
                    </x-dropdown-link>
                    <div class="my-1 border-t border-line"></div>
                    <div class="px-3 py-2">
                        <p class="mb-2 text-[11px] font-medium uppercase tracking-[0.12em] text-faint">Theme</p>
                        <div class="flex gap-1">
                            <button type="button" class="btn-secondary btn-sm flex-1" @click="setTheme('light')">Light</button>
                            <button type="button" class="btn-secondary btn-sm flex-1" @click="setTheme('dark')">Dark</button>
                            <button type="button" class="btn-secondary btn-sm flex-1" @click="setTheme('system')">Auto</button>
                        </div>
                    </div>
                    <div class="my-1 border-t border-line"></div>
                    <button
                        type="button"
                        class="w-full text-start"
                        x-on:click.prevent="async () => { if (await window.confirmLogoutIfPendingSync?.()) { $wire.logout(); } }"
                    >
                        <x-dropdown-link>
                            <x-icon name="log-out" :size="16" /> {{ __('Logout') }}
                        </x-dropdown-link>
                    </button>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</header>
