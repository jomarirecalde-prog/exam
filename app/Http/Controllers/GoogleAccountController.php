<?php

namespace App\Http\Controllers;

use App\Services\Google\GoogleIntegrationSettings;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\LinkedAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GoogleAccountController extends Controller
{
    public function __construct(
        protected GoogleIntegrationSettings $settings,
        protected GoogleOAuthService $oauth,
        protected LinkedAccountService $linkedAccounts,
    ) {
    }

    public function connect(): RedirectResponse
    {
        if (! $this->settings->signInEnabled() && ! $this->settings->registrationEnabled()) {
            abort(403, 'Google account linking is currently disabled.');
        }

        return $this->oauth->redirect(GoogleOAuthService::INTENT_LINK);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'accepted']]);

        try {
            $this->linkedAccounts->disconnectGoogleAccount($request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('profile')->withErrors($exception->errors());
        }

        return redirect()
            ->route('profile')
            ->with('status', 'Google account disconnected.');
    }
}
