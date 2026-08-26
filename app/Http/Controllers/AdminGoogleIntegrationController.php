<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\Google\GoogleIntegrationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminGoogleIntegrationController extends Controller
{
    public function __construct(
        protected GoogleIntegrationSettings $settings,
        protected AuditLogger $audit,
    ) {
    }

    public function edit(): View
    {
        return view('pages.admin.google-integration.edit', [
            'settings' => $this->settings->toArray(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sign_in_enabled' => ['nullable', 'boolean'],
            'registration_enabled' => ['nullable', 'boolean'],
            'classroom_enabled' => ['nullable', 'boolean'],
            'require_school_domain' => ['nullable', 'boolean'],
            'allowed_domain' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [
            'sign_in_enabled' => $request->boolean('sign_in_enabled'),
            'registration_enabled' => $request->boolean('registration_enabled'),
            'classroom_enabled' => $request->boolean('classroom_enabled'),
            'require_school_domain' => $request->boolean('require_school_domain'),
            'allowed_domain' => $validated['allowed_domain'] ?? '',
        ];

        $this->settings->update($payload);

        $this->audit->log($request->user(), 'google_settings_updated', 'google_integration', null, null, [
            'sign_in_enabled' => $payload['sign_in_enabled'],
            'registration_enabled' => $payload['registration_enabled'],
            'classroom_enabled' => $payload['classroom_enabled'],
            'require_school_domain' => $payload['require_school_domain'],
            'allowed_domain' => filled($payload['allowed_domain']) ? '@'.$payload['allowed_domain'] : null,
        ]);

        return redirect()
            ->route('admin.google-integration.edit')
            ->with('status', 'Google integration settings saved.');
    }
}
