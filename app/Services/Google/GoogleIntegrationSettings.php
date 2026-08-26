<?php

namespace App\Services\Google;

use App\Models\SystemSetting;

class GoogleIntegrationSettings
{
    public function signInEnabled(): bool
    {
        return (bool) SystemSetting::getValue('google_sign_in_enabled', false);
    }

    public function registrationEnabled(): bool
    {
        return (bool) SystemSetting::getValue('google_registration_enabled', false);
    }

    public function classroomEnabled(): bool
    {
        return (bool) SystemSetting::getValue('google_classroom_enabled', false);
    }

    public function requireSchoolDomain(): bool
    {
        return (bool) SystemSetting::getValue('google_require_school_domain', false);
    }

    public function allowedDomain(): ?string
    {
        $domain = SystemSetting::getValue('google_allowed_domain');

        return filled($domain) ? ltrim(strtolower(trim($domain)), '@') : null;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function isEmailDomainAllowed(string $email): bool
    {
        if (! $this->requireSchoolDomain()) {
            return true;
        }

        $allowed = $this->allowedDomain();

        if (! $allowed) {
            return true;
        }

        $domain = strtolower(str_after($email, '@'));

        return $domain === $allowed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sign_in_enabled' => $this->signInEnabled(),
            'registration_enabled' => $this->registrationEnabled(),
            'classroom_enabled' => $this->classroomEnabled(),
            'require_school_domain' => $this->requireSchoolDomain(),
            'allowed_domain' => $this->allowedDomain(),
            'is_configured' => $this->isConfigured(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        SystemSetting::setValue('google_sign_in_enabled', (bool) ($data['sign_in_enabled'] ?? false), 'boolean', 'google');
        SystemSetting::setValue('google_registration_enabled', (bool) ($data['registration_enabled'] ?? false), 'boolean', 'google');
        SystemSetting::setValue('google_classroom_enabled', (bool) ($data['classroom_enabled'] ?? false), 'boolean', 'google');
        SystemSetting::setValue('google_require_school_domain', (bool) ($data['require_school_domain'] ?? false), 'boolean', 'google');
        SystemSetting::setValue('google_allowed_domain', filled($data['allowed_domain'] ?? null) ? ltrim(trim($data['allowed_domain']), '@') : '', 'string', 'google');
    }
}
