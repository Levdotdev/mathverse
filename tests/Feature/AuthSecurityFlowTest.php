<?php

namespace Tests\Feature;

use App\Http\Middleware\SupabaseAuth;
use App\Services\SupabaseService;
use Tests\TestCase;

class AuthSecurityFlowTest extends TestCase
{
    public function test_registration_rejects_a_password_without_every_required_character_type(): void
    {
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldNotReceive('signUp');

        $response = $this->from('/')->post('/register', [
            'email' => 'student@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'role' => 'student',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'grade_level' => 6,
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('password');
    }

    public function test_password_reset_rejects_an_unregistered_email(): void
    {
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelectResult')
            ->once()
            ->with('profiles', 'id', [
                'email' => 'missing@example.com',
                'limit' => 1,
            ])
            ->andReturn([
                'data' => [],
                'error' => null,
                'status' => 200,
            ]);
        $supabase->shouldNotReceive('resetPassword');

        $response = $this->from('/')->post('/forgot-password', [
            'email' => 'Missing@Example.com',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors([
            'email' => 'No MathVerse account is registered with that email address.',
        ]);
    }

    public function test_password_reset_sends_a_link_for_a_registered_email(): void
    {
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelectResult')
            ->once()
            ->with('profiles', 'id', [
                'email' => 'student@example.com',
                'limit' => 1,
            ])
            ->andReturn([
                'data' => [['id' => 'user-id']],
                'error' => null,
                'status' => 200,
            ]);
        $supabase->shouldReceive('resetPassword')
            ->once()
            ->with('student@example.com', url('/reset-password'))
            ->andReturn([
                'successful' => true,
                'data' => [],
                'error' => null,
                'status' => 200,
            ]);

        $response = $this->from('/')->post('/forgot-password', [
            'email' => 'Student@Example.com',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('success', 'Recovery link sent.');
    }

    public function test_password_reset_failure_returns_an_error_instead_of_a_server_error(): void
    {
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelectResult')
            ->once()
            ->andReturn([
                'data' => [['id' => 'user-id']],
                'error' => null,
                'status' => 200,
            ]);
        $supabase->shouldReceive('resetPassword')
            ->once()
            ->andThrow(new \RuntimeException('Connection failed'));

        $response = $this->from('/')->post('/forgot-password', [
            'email' => 'student@example.com',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas(
            'error',
            'The recovery email could not be sent. Please try again later.'
        );
    }

    public function test_email_change_request_redirects_with_a_durable_toast_notice(): void
    {
        $this->withoutMiddleware(SupabaseAuth::class);

        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('signIn')
            ->once()
            ->with('old@example.com', 'Current1!')
            ->andReturn(['access_token' => 'fresh-token']);
        $supabase->shouldReceive('updateAuthUser')
            ->once()
            ->with(
                'fresh-token',
                ['email' => 'new@example.com'],
                url('/?auth_action=email_change')
            )
            ->andReturn([
                'successful' => true,
                'data' => [],
                'error' => null,
                'status' => 200,
            ]);
        $supabase->shouldReceive('audit')->once()->andReturn(true);

        $response = $this->withSession([
            'supabase_token' => 'old-token',
            'supabase_user' => [
                'id' => 'user-id',
                'role' => 'student',
                'email' => 'old@example.com',
            ],
        ])->post('/change-email', [
            'current_password' => 'Current1!',
            'new_email' => 'New@Example.com',
            'new_email_confirmation' => 'New@Example.com',
        ]);

        $response->assertRedirect('/student/dashboard?section=security&notice=email-change-requested');
        $response->assertSessionHas(
            'success',
            'Email change requested. Check your new email address to confirm the change.'
        );
    }

    public function test_email_change_notice_is_rendered_without_relying_on_flash_session_data(): void
    {
        $this->mock(SupabaseService::class);

        $response = $this->get('/?notice=email-change-requested');

        $response->assertOk();
        $response->assertSee('Email change requested. Check your new email address to confirm the change.');
    }

    public function test_signup_confirmation_return_shows_a_success_toast(): void
    {
        $this->mock(SupabaseService::class);

        $response = $this->get('/?auth_action=signup');

        $response->assertOk();
        $response->assertSee('Email confirmed successfully. You can now sign in.');
    }

    public function test_email_change_confirmation_keeps_the_session_and_redirects_with_a_toast(): void
    {
        $this->mock(SupabaseService::class);

        $response = $this->withSession([
            'supabase_token' => 'old-token',
            'supabase_user' => [
                'id' => 'user-id',
                'role' => 'student',
                'email' => 'old@example.com',
            ],
        ])->get('/?auth_action=email_change');

        $response->assertRedirect('/student/dashboard');
        $response->assertSessionHas('supabase_token', 'old-token');
        $response->assertSessionHas('supabase_user');
        $response->assertSessionHas(
            'success',
            'Email address changed successfully.'
        );
    }
}
