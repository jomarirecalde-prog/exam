<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }
}; ?>

<div>
    <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
    <p class="mt-2 text-sm leading-6 text-muted">Sign in to continue to your examination dashboard.</p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form wire:submit="login" class="mt-8 space-y-5">
        <x-ui.field label="Email / Student ID" for="email" :error="$errors->first('form.email')">
            <x-text-input wire:model="form.email" id="email" type="text" name="email" required autofocus autocomplete="username" />
        </x-ui.field>

        <x-ui.field label="Password" for="password" :error="$errors->first('form.password')">
            <x-text-input wire:model="form.password" id="password" type="password" name="password" required autocomplete="current-password" />
        </x-ui.field>

        <label for="remember" class="flex items-center gap-2 text-sm text-muted">
            <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-line text-navy-800 focus:ring-navy-700" name="remember">
            Remember me
        </label>

        <x-primary-button class="w-full">Sign In</x-primary-button>

        @if (Route::has('password.request'))
            <p class="text-center">
                <a class="text-sm font-medium text-ink underline-offset-4 hover:underline" href="{{ route('password.request') }}" wire:navigate>Forgot Password</a>
            </p>
        @endif
    </form>
</div>
