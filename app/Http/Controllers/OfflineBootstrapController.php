<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class OfflineBootstrapController extends Controller
{
    public function app(Request $request): View
    {
        $user = $request->user();

        abort_unless($user?->hasRole('student') && $user->student, 403);

        return view('pages.offline.app', [
            'student' => $user->student,
            'userName' => $user->fullName() ?: $user->name,
        ]);
    }

    public function takeExam(Request $request, int $examination): View
    {
        $user = $request->user();

        abort_unless($user?->hasRole('student') && $user->student, 403);

        return view('pages.offline.exam-take', [
            'examinationId' => $examination,
            'studentId' => $user->student->id,
            'studentName' => $user->fullName() ?: $user->name,
        ]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $user?->student;

        if (! $student) {
            return response()->json(['message' => 'Only students can initialize offline access.'], 403);
        }

        $validated = $request->validate([
            'device_identifier' => ['required', 'string', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $ttlHours = (int) config('examination.offline_session_hours', 168);
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addHours($ttlHours);

        $payload = [
            'user_id' => $user->id,
            'student_id' => $student->id,
            'student_name' => $user->fullName() ?: $user->name,
            'device_identifier' => $validated['device_identifier'],
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        $encoded = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encoded, (string) config('app.key'));

        return response()->json([
            'session' => [
                ...$payload,
                'token' => $encoded.'.'.$signature,
            ],
            'offline_app_url' => route('offline.app'),
            'sync_status_url' => route('offline.sync'),
            'shell_urls' => [
                route('offline.app'),
                route('dashboard'),
                route('examinations.index'),
                route('offline.sync'),
            ],
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function setAppPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'offline_app_pin_hash' => Hash::make($validated['pin']),
        ])->save();

        return response()->json(['message' => 'Application PIN updated.']);
    }

    public function clearAppPin(Request $request): JsonResponse
    {
        $request->user()->forceFill(['offline_app_pin_hash' => null])->save();

        return response()->json(['message' => 'Application PIN removed.']);
    }

    public function pinConfigured(Request $request): JsonResponse
    {
        return response()->json([
            'configured' => ! empty($request->user()->offline_app_pin_hash),
        ]);
    }
}
