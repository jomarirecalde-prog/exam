<?php

namespace App\Http\Controllers;

use App\Models\GoogleClassroomCourseLink;
use App\Services\AuditLogger;
use App\Services\Google\GoogleClassroomMatchingService;
use App\Services\Google\GoogleClassroomService;
use App\Services\Google\GoogleIntegrationSettings;
use App\Services\Google\GoogleOAuthService;
use App\Services\Students\AcademicLookupService;
use App\Services\Students\SubjectOfferingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleClassroomController extends Controller
{
    public function __construct(
        protected GoogleIntegrationSettings $settings,
        protected GoogleOAuthService $oauth,
        protected GoogleClassroomService $classroom,
        protected GoogleClassroomMatchingService $matching,
        protected SubjectOfferingService $offerings,
        protected AcademicLookupService $academic,
        protected AuditLogger $audit,
    ) {
    }

    public function index(): View
    {
        $user = Auth::user();
        $student = $user->student;

        abort_unless($student, 403);

        return view('pages.google-classroom.index', [
            'connection' => $user->googleClassroomConnection,
            'courseLinks' => $user->googleClassroomCourseLinks()->with('subjectOffering.subject', 'subjectOffering.instructor.user', 'subjectOffering.section')->get(),
            'classroomEnabled' => $this->settings->classroomEnabled(),
        ]);
    }

    public function connect(): RedirectResponse
    {
        if (! $this->settings->classroomEnabled()) {
            abort(403, 'Google Classroom integration is currently disabled.');
        }

        return $this->oauth->redirect(
            GoogleOAuthService::INTENT_CLASSROOM,
            GoogleClassroomService::CLASSROOM_SCOPES,
            config('services.google.classroom_redirect'),
        );
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()
                ->route('google-classroom.index')
                ->with('google_error', 'You denied Google Classroom access.');
        }

        try {
            $googleUser = $this->oauth->handleCallback(
                $request->query('state'),
                config('services.google.classroom_redirect'),
            );
            $this->oauth->validateGoogleUser($googleUser);
            $this->classroom->storeConnection(Auth::user(), $googleUser);
        } catch (InvalidStateException) {
            return redirect()
                ->route('google-classroom.index')
                ->with('google_error', 'Google Classroom authorization expired. Please try again.');
        } catch (ValidationException $exception) {
            return redirect()->route('google-classroom.index')->withErrors($exception->errors());
        }

        return redirect()
            ->route('google-classroom.import')
            ->with('status', 'Google Classroom connected. Select the classes you want to import.');
    }

    public function import(): View|RedirectResponse
    {
        $user = Auth::user();

        if (! $user->googleClassroomConnection) {
            return redirect()->route('google-classroom.index');
        }

        try {
            $courses = $this->classroom->fetchCourses($user);
        } catch (ValidationException $exception) {
            return redirect()->route('google-classroom.index')->withErrors($exception->errors());
        }

        $student = $user->student;
        $matches = $this->matching->matchCourses(
            $courses,
            $student?->section_id,
            $student?->program?->department_id,
        );

        return view('pages.google-classroom.import', [
            'matches' => $matches,
            'manualOfferings' => $this->manualOfferings($student),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.google_course_id' => ['required', 'string'],
            'selections.*.course_name' => ['required', 'string'],
            'selections.*.course_section' => ['nullable', 'string'],
            'selections.*.instructor_name' => ['nullable', 'string'],
            'selections.*.subject_offering_id' => ['nullable', 'integer', 'exists:subject_instructor,id'],
            'selections.*.match_confidence' => ['nullable', 'string'],
            'selections.*.manual' => ['nullable', 'boolean'],
        ]);

        $user = Auth::user();

        foreach ($validated['selections'] as $selection) {
            GoogleClassroomCourseLink::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'google_course_id' => $selection['google_course_id'],
                ],
                [
                    'course_name' => $selection['course_name'],
                    'course_section' => $selection['course_section'] ?? null,
                    'instructor_name' => $selection['instructor_name'] ?? null,
                    'subject_offering_id' => $selection['subject_offering_id'] ?? null,
                    'match_confidence' => $selection['match_confidence'] ?? null,
                    'confirmed' => filled($selection['subject_offering_id']),
                ],
            );

            $action = ! empty($selection['manual'])
                ? 'google_classroom_manual_subject_selected'
                : 'google_classroom_match_confirmed';

            $this->audit->log($user, $action, 'google_integration', GoogleClassroomCourseLink::class, $selection['google_course_id'], [
                'course_name' => $selection['course_name'],
                'subject_offering_id' => $selection['subject_offering_id'] ?? null,
            ]);
        }

        return redirect()
            ->route('google-classroom.index')
            ->with('status', 'Selected Google Classroom courses have been saved for subject matching.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'accepted']]);

        try {
            $this->classroom->disconnect($request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('google-classroom.index')->withErrors($exception->errors());
        }

        return redirect()
            ->route('google-classroom.index')
            ->with('status', 'Google Classroom disconnected.');
    }

    public function offerings(Request $request): JsonResponse
    {
        $student = $request->user()->student;
        abort_unless($student?->section_id, 422);

        $groups = $this->offerings->offeringsForRegistration(
            (int) $student->section_id,
            (int) ($student->program?->department_id ?? 0),
            $request->query('search'),
            $request->boolean('browse_all'),
        );

        return response()->json($groups);
    }

    /**
     * @return array<string, mixed>
     */
    protected function manualOfferings(?\App\Models\Student $student): array
    {
        if (! $student?->section_id) {
            return ['recommended' => [], 'other' => []];
        }

        return $this->offerings->offeringsForRegistration(
            (int) $student->section_id,
            (int) ($student->program?->department_id ?? 0),
        );
    }
}
