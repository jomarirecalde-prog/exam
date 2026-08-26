<?php

namespace Tests\Feature;

use App\Enums\AuthProvider;
use App\Enums\StudentRegistrationStatus;
use App\Enums\UserRole;
use App\Models\LinkedAccount;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Mockery;

class GoogleIntegrationTest extends StudentRegistrationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        SystemSetting::setValue('google_sign_in_enabled', true, 'boolean', 'google');
        SystemSetting::setValue('google_registration_enabled', true, 'boolean', 'google');
        SystemSetting::setValue('google_classroom_enabled', true, 'boolean', 'google');
    }

    public function test_login_page_shows_google_button_when_enabled(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue with Google');
    }

    public function test_google_redirect_requires_configuration(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('auth.google'))
            ->assertStatus(503);
    }

    public function test_existing_linked_google_account_logs_in(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@gmail.com',
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::Student->value);

        LinkedAccount::create([
            'user_id' => $user->id,
            'provider' => AuthProvider::Google,
            'provider_account_id' => 'google-123',
            'provider_email' => 'linked@gmail.com',
            'connected_at' => now(),
        ]);

        $googleUser = $this->mockGoogleUser('google-123', 'linked@gmail.com');
        $this->mockSocialiteRedirectAndCallback($googleUser);

        $this->withSession([GoogleOAuthService::SESSION_STATE => 'valid-state'])
            ->get(route('auth.google.callback', ['state' => 'valid-state']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_new_google_user_is_sent_to_registration_completion(): void
    {
        $googleUser = $this->mockGoogleUser('google-new', 'newstudent@gmail.com', 'New Student');
        $this->mockSocialiteRedirectAndCallback($googleUser);

        $this->withSession([
            GoogleOAuthService::SESSION_STATE => 'valid-state',
            GoogleOAuthService::SESSION_INTENT => GoogleOAuthService::INTENT_REGISTER,
        ])->get(route('auth.google.callback', ['state' => 'valid-state']))
            ->assertRedirect(route('google-registration.create'));
    }

    public function test_google_registration_completion_creates_pending_student(): void
    {
        $structure = $this->academicStructure();

        session([
            GoogleOAuthService::SESSION_PROFILE => [
                'provider_account_id' => 'google-new',
                'email' => 'newstudent@gmail.com',
                'name' => 'New Student',
                'first_name' => 'New',
                'last_name' => 'Student',
                'stored_at' => now()->timestamp,
            ],
        ]);

        $payload = $this->registrationPayload($structure);
        unset($payload['password'], $payload['password_confirmation']);
        $payload['email'] = 'newstudent@gmail.com';
        $payload['first_name'] = 'New';
        $payload['last_name'] = 'Student';

        $response = $this->post(route('google-registration.store'), $payload);

        $student = Student::query()->where('student_id', $payload['student_id'])->first();

        $this->assertNotNull($student);
        $this->assertSame(StudentRegistrationStatus::Pending, $student->registration_status);
        $this->assertTrue($student->user->linkedAccounts()->where('provider', AuthProvider::Google)->exists());
        $response->assertRedirect(route('student-registration.confirmation', ['student' => $student->id]));
    }

    public function test_duplicate_google_account_cannot_link_to_second_user(): void
    {
        $first = User::factory()->create(['is_active' => true]);
        $first->assignRole(UserRole::Student->value);

        LinkedAccount::create([
            'user_id' => $first->id,
            'provider' => AuthProvider::Google,
            'provider_account_id' => 'google-shared',
            'provider_email' => 'shared@gmail.com',
            'connected_at' => now(),
        ]);

        $second = User::factory()->create(['is_active' => true, 'password' => Hash::make('Password123!')]);
        $second->assignRole(UserRole::Student->value);

        $googleUser = $this->mockGoogleUser('google-shared', 'shared@gmail.com');
        $this->mockSocialiteRedirectAndCallback($googleUser);

        $this->actingAs($second)
            ->withSession([
                GoogleOAuthService::SESSION_STATE => 'valid-state',
                GoogleOAuthService::SESSION_INTENT => GoogleOAuthService::INTENT_LINK,
            ])
            ->get(route('auth.google.callback', ['state' => 'valid-state']))
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('google');
    }

    public function test_student_can_disconnect_google_when_password_exists(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::Student->value);

        LinkedAccount::create([
            'user_id' => $user->id,
            'provider' => AuthProvider::Google,
            'provider_account_id' => 'google-disconnect',
            'provider_email' => 'student@gmail.com',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('account.google.disconnect'), ['confirm' => '1'])
            ->assertRedirect(route('profile'));

        $this->assertDatabaseMissing('linked_accounts', [
            'user_id' => $user->id,
            'provider' => AuthProvider::Google->value,
        ]);
    }

    public function test_student_cannot_disconnect_google_without_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
            'password_login_enabled' => false,
            'is_active' => true,
        ]);
        $user->assignRole(UserRole::Student->value);

        LinkedAccount::create([
            'user_id' => $user->id,
            'provider' => AuthProvider::Google,
            'provider_account_id' => 'google-only',
            'provider_email' => 'onlygoogle@gmail.com',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('account.google.disconnect'), ['confirm' => '1'])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('google');
    }

    public function test_school_domain_restriction_rejects_unapproved_email(): void
    {
        SystemSetting::setValue('google_require_school_domain', true, 'boolean', 'google');
        SystemSetting::setValue('google_allowed_domain', 'school.edu', 'string', 'google');

        $googleUser = $this->mockGoogleUser('google-domain', 'student@gmail.com');
        $this->mockSocialiteRedirectAndCallback($googleUser);

        $this->withSession([GoogleOAuthService::SESSION_STATE => 'valid-state'])
            ->get(route('auth.google.callback', ['state' => 'valid-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('google_error');
    }

    public function test_admin_can_update_google_settings(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(UserRole::Superadmin->value);

        $this->actingAs($admin)
            ->put(route('admin.google-integration.update'), [
                'sign_in_enabled' => '1',
                'registration_enabled' => '1',
                'classroom_enabled' => '1',
                'require_school_domain' => '1',
                'allowed_domain' => 'school.edu',
            ])
            ->assertRedirect(route('admin.google-integration.edit'));

        $this->assertTrue((bool) SystemSetting::getValue('google_sign_in_enabled'));
        $this->assertSame('school.edu', SystemSetting::getValue('google_allowed_domain'));
    }

    public function test_oauth_callback_with_invalid_state_is_rejected(): void
    {
        $googleUser = $this->mockGoogleUser('google-state', 'state@gmail.com');
        $this->mockSocialiteRedirectAndCallback($googleUser);

        $this->withSession([GoogleOAuthService::SESSION_STATE => 'expected-state'])
            ->get(route('auth.google.callback', ['state' => 'wrong-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');
    }

    public function test_oauth_cancellation_shows_friendly_message(): void
    {
        $this->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('google_error');
    }

    protected function mockGoogleUser(string $id, string $email, string $name = 'Test User'): object
    {
        $googleUser = Mockery::mock('Laravel\Socialite\Contracts\User');
        $googleUser->shouldReceive('getId')->andReturn($id);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn($name);
        $googleUser->shouldReceive('getAvatar')->andReturn(null);
        $googleUser->user = [
            'given_name' => explode(' ', $name)[0],
            'family_name' => explode(' ', $name)[1] ?? '',
        ];

        return $googleUser;
    }

    protected function mockSocialiteRedirectAndCallback(object $googleUser): void
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com'));
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('with')->andReturnSelf();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
