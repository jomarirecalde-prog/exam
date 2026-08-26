<?php

namespace App\Services\Google;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class GoogleOAuthService
{
    public const SESSION_STATE = 'google_oauth_state';
    public const SESSION_INTENT = 'google_oauth_intent';
    public const SESSION_PROFILE = 'google_registration_profile';

    public const INTENT_LOGIN = 'login';
    public const INTENT_REGISTER = 'register';
    public const INTENT_LINK = 'link';
    public const INTENT_CLASSROOM = 'classroom';

    public function __construct(
        protected GoogleIntegrationSettings $settings,
    ) {
    }

    /**
     * @param  list<string>  $scopes
     */
    public function redirect(string $intent, array $scopes = [], ?string $redirectUri = null): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $state = Str::random(40);
        Session::put(self::SESSION_STATE, $state);
        Session::put(self::SESSION_INTENT, $intent);

        $driver = Socialite::driver('google')
            ->scopes($scopes ?: ['openid', 'profile', 'email'])
            ->with(['state' => $state, 'access_type' => 'offline', 'prompt' => $this->promptForIntent($intent)]);

        if ($redirectUri) {
            $driver->redirectUrl($redirectUri);
        }

        return $driver->redirect();
    }

    public function handleCallback(?string $state, ?string $redirectUri = null): SocialiteUser
    {
        $expectedState = Session::pull(self::SESSION_STATE);

        if (! $expectedState || ! hash_equals($expectedState, (string) $state)) {
            throw ValidationException::withMessages([
                'google' => 'Google sign-in session expired or was invalid. Please try again.',
            ]);
        }

        $driver = Socialite::driver('google');

        if ($redirectUri) {
            $driver->redirectUrl($redirectUri);
        }

        return $driver->user();
    }

    public function pullIntent(): ?string
    {
        return Session::pull(self::SESSION_INTENT);
    }

    public function storeRegistrationProfile(SocialiteUser $googleUser): void
    {
        Session::put(self::SESSION_PROFILE, [
            'provider_account_id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName(),
            'avatar' => $googleUser->getAvatar(),
            'first_name' => $this->extractFirstName($googleUser),
            'last_name' => $this->extractLastName($googleUser),
            'stored_at' => now()->timestamp,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function registrationProfile(): ?array
    {
        $profile = Session::get(self::SESSION_PROFILE);

        if (! is_array($profile)) {
            return null;
        }

        $storedAt = (int) ($profile['stored_at'] ?? 0);

        if ($storedAt <= 0 || now()->timestamp - $storedAt > 3600) {
            Session::forget(self::SESSION_PROFILE);

            return null;
        }

        return $profile;
    }

    public function clearRegistrationProfile(): void
    {
        Session::forget(self::SESSION_PROFILE);
    }

    public function validateGoogleUser(SocialiteUser $googleUser): void
    {
        if (! $googleUser->getEmail()) {
            throw ValidationException::withMessages([
                'google' => 'Your Google account did not provide a verified email address. Please use a different account or contact support.',
            ]);
        }

        if (! $this->settings->isEmailDomainAllowed($googleUser->getEmail())) {
            $domain = $this->settings->allowedDomain();

            throw ValidationException::withMessages([
                'google' => "Only Google accounts from @{$domain} are allowed for this institution.",
            ]);
        }
    }

    public function loginUser(User $user, bool $remember = false): void
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'google' => $this->inactiveAccountMessage($user),
            ]);
        }

        if ($user->isLocked()) {
            throw ValidationException::withMessages([
                'google' => 'Your account is temporarily locked. Please try again later.',
            ]);
        }

        Auth::login($user, $remember);
        Session::regenerate();

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
    }

    protected function promptForIntent(string $intent): string
    {
        return match ($intent) {
            self::INTENT_CLASSROOM => 'consent',
            self::INTENT_LINK => 'select_account',
            default => 'select_account',
        };
    }

    protected function extractFirstName(SocialiteUser $googleUser): string
    {
        $raw = $googleUser->user ?? [];

        if (filled($raw['given_name'] ?? null)) {
            return trim($raw['given_name']);
        }

        return trim(explode(' ', (string) $googleUser->getName())[0] ?? '');
    }

    protected function extractLastName(SocialiteUser $googleUser): string
    {
        $raw = $googleUser->user ?? [];

        if (filled($raw['family_name'] ?? null)) {
            return trim($raw['family_name']);
        }

        $parts = explode(' ', trim((string) $googleUser->getName()));

        return count($parts) > 1 ? trim(implode(' ', array_slice($parts, 1))) : '';
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
