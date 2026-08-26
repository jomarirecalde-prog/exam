<?php

namespace App\Services\Google;

use App\Models\GoogleClassroomConnection;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleClassroomService
{
    public const CLASSROOM_SCOPES = [
        'openid',
        'profile',
        'email',
        'https://www.googleapis.com/auth/classroom.courses.readonly',
        'https://www.googleapis.com/auth/classroom.rosters.readonly',
    ];

    public function __construct(
        protected GoogleIntegrationSettings $settings,
        protected AuditLogger $audit,
    ) {
    }

    public function storeConnection(User $user, SocialiteUser $googleUser): GoogleClassroomConnection
    {
        $token = $googleUser->token ?? null;
        $refreshToken = $googleUser->refreshToken ?? null;
        $expiresIn = $googleUser->expiresIn ?? null;

        if (! $token) {
            throw ValidationException::withMessages([
                'google' => 'Google Classroom authorization did not return an access token. Please try again.',
            ]);
        }

        $connection = GoogleClassroomConnection::updateOrCreate(
            ['user_id' => $user->id],
            [
                'google_account_id' => $googleUser->getId(),
                'google_email' => $googleUser->getEmail(),
                'scopes' => implode(' ', self::CLASSROOM_SCOPES),
                'access_token' => $token,
                'refresh_token' => $refreshToken ?: GoogleClassroomConnection::query()->where('user_id', $user->id)->value('refresh_token'),
                'token_expires_at' => $expiresIn ? now()->addSeconds((int) $expiresIn) : null,
                'is_active' => true,
                'connected_at' => now(),
            ],
        );

        $this->audit->log($user, 'google_classroom_connected', 'google_integration', GoogleClassroomConnection::class, $connection->id, [
            'google_email' => $connection->google_email,
        ]);

        return $connection;
    }

    public function disconnect(User $user): void
    {
        $connection = $user->googleClassroomConnection;

        if (! $connection) {
            throw ValidationException::withMessages([
                'google' => 'Google Classroom is not connected.',
            ]);
        }

        $connection->delete();

        $user->googleClassroomCourseLinks()->delete();

        $this->audit->log($user, 'google_classroom_disconnected', 'google_integration', GoogleClassroomConnection::class, null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchCourses(User $user): array
    {
        $connection = $user->googleClassroomConnection;

        if (! $connection || ! $connection->is_active) {
            throw ValidationException::withMessages([
                'google' => 'Connect Google Classroom before retrieving courses.',
            ]);
        }

        $token = $this->resolveAccessToken($connection);
        $courses = [];
        $pageToken = null;

        do {
            $response = Http::withToken($token)
                ->get('https://classroom.googleapis.com/v1/courses', array_filter([
                    'courseStates' => 'ACTIVE',
                    'pageSize' => 30,
                    'pageToken' => $pageToken,
                ]));

            if ($response->failed()) {
                throw ValidationException::withMessages([
                    'google' => 'Unable to retrieve Google Classroom courses. Please reconnect and try again.',
                ]);
            }

            foreach ($response->json('courses', []) as $course) {
                $courses[] = $this->formatCourse($course, $token);
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        $connection->update(['last_synced_at' => now()]);

        $this->audit->log($user, 'google_classroom_sync_completed', 'google_integration', GoogleClassroomConnection::class, $connection->id, [
            'course_count' => count($courses),
        ]);

        return $courses;
    }

    protected function resolveAccessToken(GoogleClassroomConnection $connection): string
    {
        if (! $connection->isTokenExpired()) {
            return (string) $connection->access_token;
        }

        if (! $connection->refresh_token) {
            throw ValidationException::withMessages([
                'google' => 'Your Google Classroom authorization has expired. Please reconnect.',
            ]);
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'google' => 'Your Google Classroom authorization has expired. Please reconnect.',
            ]);
        }

        $connection->update([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return (string) $response->json('access_token');
    }

    /**
     * @param  array<string, mixed>  $course
     * @return array<string, mixed>
     */
    protected function formatCourse(array $course, string $token): array
    {
        $courseId = $course['id'] ?? '';
        $teachers = [];

        if ($courseId) {
            $teacherResponse = Http::withToken($token)
                ->get("https://classroom.googleapis.com/v1/courses/{$courseId}/teachers", ['pageSize' => 5]);

            if ($teacherResponse->successful()) {
                foreach ($teacherResponse->json('teachers', []) as $teacher) {
                    $profile = $teacher['profile'] ?? [];
                    $name = $profile['name'] ?? [];
                    $fullName = trim(collect([$name['givenName'] ?? '', $name['familyName'] ?? ''])->filter()->implode(' '));

                    if ($fullName !== '') {
                        $teachers[] = $fullName;
                    }
                }
            }
        }

        return [
            'id' => $courseId,
            'name' => $course['name'] ?? 'Untitled Course',
            'section' => $course['section'] ?? null,
            'instructors' => $teachers,
            'instructor_name' => $teachers[0] ?? null,
        ];
    }
}
