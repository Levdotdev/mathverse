<?php

namespace Tests\Feature;

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
            'Email confirmation received. Complete any other confirmation link to finish updating your email address.'
        );
    }
}
