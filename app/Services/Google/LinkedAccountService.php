<?php

namespace App\Services\Google;

use App\Enums\AuthProvider;
use App\Models\LinkedAccount;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class LinkedAccountService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {
    }

    public function findByProviderAccount(AuthProvider $provider, string $providerAccountId): ?LinkedAccount
    {
        return LinkedAccount::query()
            ->where('provider', $provider)
            ->where('provider_account_id', $providerAccountId)
            ->first();
    }

    public function findGoogleAccount(string $providerAccountId): ?LinkedAccount
    {
        return $this->findByProviderAccount(AuthProvider::Google, $providerAccountId);
    }

    public function linkGoogleAccount(User $user, SocialiteUser $googleUser): LinkedAccount
    {
        return $this->linkGoogleProfile($user, [
            'provider_account_id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName(),
            'avatar' => $googleUser->getAvatar(),
        ]);
    }

    /**
     * @param  array{provider_account_id: string, email?: ?string, name?: ?string, avatar?: ?string}  $profile
     */
    public function linkGoogleProfile(User $user, array $profile): LinkedAccount
    {
        $existing = $this->findGoogleAccount($profile['provider_account_id']);

        if ($existing && $existing->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'google' => 'This Google account is already linked to another user account.',
            ]);
        }

        $account = DB::transaction(function () use ($user, $profile, $existing) {
            if ($existing) {
                return $existing->fresh();
            }

            return LinkedAccount::create([
                'user_id' => $user->id,
                'provider' => AuthProvider::Google,
                'provider_account_id' => $profile['provider_account_id'],
                'provider_email' => $profile['email'] ?? null,
                'provider_name' => $profile['name'] ?? null,
                'provider_avatar' => $profile['avatar'] ?? null,
                'connected_at' => now(),
            ]);
        });

        $this->audit->log($user, 'google_account_connected', 'google_integration', LinkedAccount::class, $account->id, [
            'provider_email' => $account->provider_email,
        ]);

        return $account;
    }

    public function disconnectGoogleAccount(User $user): void
    {
        $linked = $user->linkedAccounts()->where('provider', AuthProvider::Google)->first();

        if (! $linked) {
            throw ValidationException::withMessages([
                'google' => 'No Google account is linked to this profile.',
            ]);
        }

        if (! $this->canDisconnectGoogle($user)) {
            throw ValidationException::withMessages([
                'google' => 'You must set a password before disconnecting your Google account, so you can still sign in.',
            ]);
        }

        $email = $linked->provider_email;

        $linked->delete();

        $this->audit->log($user, 'google_account_disconnected', 'google_integration', LinkedAccount::class, null, [
            'provider_email' => $email,
        ]);
    }

    public function canDisconnectGoogle(User $user): bool
    {
        $hasGoogle = $user->linkedAccounts()->where('provider', AuthProvider::Google)->exists();

        if (! $hasGoogle) {
            return false;
        }

        return $user->hasPassword();
    }

    public function googleAccountFor(User $user): ?LinkedAccount
    {
        return $user->linkedAccounts()->where('provider', AuthProvider::Google)->first();
    }
}
