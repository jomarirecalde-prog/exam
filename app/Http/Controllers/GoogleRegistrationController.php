<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoogleStudentRegistrationRequest;
use App\Services\Google\GoogleIntegrationSettings;
use App\Services\Google\GoogleOAuthService;
use App\Services\Students\AcademicLookupService;
use App\Services\Students\StudentRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GoogleRegistrationController extends Controller
{
    public function __construct(
        protected GoogleIntegrationSettings $settings,
        protected GoogleOAuthService $oauth,
        protected AcademicLookupService $academic,
        protected StudentRegistrationService $registrations,
    ) {
    }

    public function create(): View|RedirectResponse
    {
        if (! $this->settings->registrationEnabled()) {
            abort(403, 'Google registration is currently disabled.');
        }

        $profile = $this->oauth->registrationProfile();

        if (! $profile) {
            return redirect()
                ->route('student-registration.create')
                ->with('google_error', 'Your Google registration session expired. Please authenticate with Google again.');
        }

        return view('pages.student-registration.create', [
            'departments' => $this->academic->activeDepartments(),
            'googleProfile' => $profile,
            'googleMode' => true,
            'pageTitle' => 'Complete Your Student Profile',
            'pageDescription' => 'Finish your registration with required school information. Google authentication alone does not complete enrollment.',
            'formAction' => route('google-registration.store'),
        ]);
    }

    public function store(StoreGoogleStudentRegistrationRequest $request): RedirectResponse
    {
        $profile = $this->oauth->registrationProfile();

        if (! $profile) {
            return redirect()
                ->route('student-registration.create')
                ->with('google_error', 'Your Google registration session expired. Please authenticate with Google again.');
        }

        $student = $this->registrations->registerWithGoogle($request->validated(), $profile);
        $this->oauth->clearRegistrationProfile();

        return redirect()
            ->route('student-registration.confirmation', ['student' => $student->id])
            ->with('registered_student_id', $student->student_id);
    }
}
