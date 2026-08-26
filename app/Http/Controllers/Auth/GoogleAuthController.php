<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Google\GoogleIntegrationSettings;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\LinkedAccountService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function __construct(
        protected GoogleIntegrationSettings $settings,
        protected GoogleOAuthService $oauth,
        protected LinkedAccountService $linkedAccounts,
    ) {
    }

    public function redirect(Request $request): RedirectResponse
    {
        $this->ensureConfigured();

        if (! $this->settings->signInEnabled() && ! $this->settings->registrationEnabled()) {
            abort(403, 'Google sign-in is currently disabled.');
        }

        $intent = $request->query('intent', GoogleOAuthService::INTENT_LOGIN);

        if ($intent === GoogleOAuthService::INTENT_REGISTER && ! $this->settings->registrationEnabled()) {
            abort(403, 'Google registration is currently disabled.');
        }

        return $this->oauth->redirect($intent);
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->ensureConfigured();

        if ($request->has('error')) {
            return redirect()
                ->route('login')
                ->with('google_error', 'You cancelled the Google sign-in process.');
        }

        $intent = Session::get(GoogleOAuthService::SESSION_INTENT, GoogleOAuthService::INTENT_LOGIN);

        try {
            $googleUser = $this->oauth->handleCallback($request->query('state'));
            $this->oauth->validateGoogleUser($googleUser);
        } catch (InvalidStateException) {
            return redirect()
                ->route('login')
                ->with('google_error', 'Google sign-in session expired or was invalid. Please try again.');
        } catch (ValidationException $exception) {
            Session::forget(GoogleOAuthService::SESSION_INTENT);
            $message = collect($exception->errors())->flatten()->first();

            return redirect()
                ->route($intent === GoogleOAuthService::INTENT_LINK ? 'profile' : 'login')
                ->withErrors($exception->errors())
                ->with('google_error', $message);
        } catch (\Throwable $exception) {
            Log::warning('Google OAuth callback failed.', ['error' => $exception->getMessage()]);

            return redirect()
                ->route('login')
                ->with('google_error', 'Google sign-in failed. Please try again.');
        }

        Session::forget(GoogleOAuthService::SESSION_INTENT);

        if ($intent === GoogleOAuthService::INTENT_LINK) {
            if (! Auth::check()) {
                return redirect()->route('login')->with('google_error', 'Sign in before linking a Google account.');
            }

            try {
                $this->linkedAccounts->linkGoogleAccount(Auth::user(), $googleUser);
            } catch (ValidationException $exception) {
                return redirect()->route('profile')->withErrors($exception->errors());
            }

            return redirect()->route('profile')->with('status', 'Google account connected successfully.');
        }

        $linked = $this->linkedAccounts->findGoogleAccount($googleUser->getId());

        if ($linked) {
            try {
                $this->oauth->loginUser($linked->user);
            } catch (ValidationException $exception) {
                return redirect()->route('login')->withErrors($exception->errors());
            }

            return redirect()->intended(route('dashboard'));
        }

        if ($intent === GoogleOAuthService::INTENT_REGISTER || $this->settings->registrationEnabled()) {
            $existingUser = User::query()->where('email', $googleUser->getEmail())->first();

            if ($existingUser) {
                return redirect()
                    ->route('login')
                    ->with('google_error', 'An account with this email already exists. Sign in with your password, then link Google from your profile.');
            }

            $this->oauth->storeRegistrationProfile($googleUser);

            return redirect()->route('google-registration.create');
        }

        return redirect()
            ->route('login')
            ->with('google_error', 'No linked account found for this Google account. Register as a student first or sign in with your password.');
    }

    protected function ensureConfigured(): void
    {
        if (! $this->settings->isConfigured()) {
            abort(503, 'Google sign-in is not configured.');
        }
    }
}
