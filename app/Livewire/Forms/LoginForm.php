<?php

namespace App\Livewire\Forms;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = $this->resolveUser();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            $this->recordFailedAttempt($user);
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        if ($user->isLocked()) {
            throw ValidationException::withMessages([
                'form.email' => 'Your account is temporarily locked. Please try again later.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'form.email' => $this->inactiveAccountMessage($user),
            ]);
        }

        Auth::login($user, $this->remember);

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        LoginLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'successful' => true,
            'logged_in_at' => now(),
        ]);

        RateLimiter::clear($this->throttleKey());
    }

    protected function resolveUser(): ?User
    {
        $identifier = trim($this->email);

        return User::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->orWhereHas('student', fn ($query) => $query->where('student_id', $identifier))
            ->first();
    }

    protected function recordFailedAttempt(?User $user): void
    {
        LoginLog::create([
            'user_id' => $user?->id,
            'email' => $this->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'successful' => false,
            'failure_reason' => 'invalid_credentials',
            'logged_in_at' => now(),
        ]);

        if (! $user) {
            return;
        }

        $attempts = $user->failed_login_attempts + 1;
        $maxAttempts = (int) config('examination.max_login_attempts', 5);

        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $attempts >= $maxAttempts
                ? now()->addMinutes((int) config('examination.lockout_minutes', 15))
                : $user->locked_until,
        ])->save();
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    protected function inactiveAccountMessage(User $user): string
    {
        $student = $user->student;

        if ($student?->isRegistrationPending()) {
            return 'Your registration has been submitted successfully and is awaiting administrator approval.';
        }

        if ($student?->isRegistrationRejected()) {
            return filled($student->rejection_reason)
                ? $student->rejection_reason
                : 'Your registration requires additional information. Please contact the administrator.';
        }

        return 'Your account has been deactivated.';
    }
}
