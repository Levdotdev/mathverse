<?php

namespace Tests\Feature;

use App\Http\Middleware\SupabaseAuth;
use App\Services\SupabaseService;
use Mockery;
use Tests\TestCase;

class HorizontalAuthorizationTest extends TestCase
{
    private const ACTOR_ID = '11111111-1111-4111-8111-111111111111';
    private const TARGET_ID = '22222222-2222-4222-8222-222222222222';
    private const SESSION_ID = '33333333-3333-4333-8333-333333333333';

    public function test_teacher_cannot_open_another_teachers_class(): void
    {
        $this->withoutMiddleware(SupabaseAuth::class);
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->with('classes', '*', [
                'id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
            ])
            ->andReturn([]);

        $response = $this->withSession(['supabase_user' => $this->teacher()])
            ->get('/teacher/classes/' . self::TARGET_ID);

        $response->assertRedirect('/teacher/dashboard?section=classes');
        $response->assertSessionHas('error', 'Class not found.');
    }

    public function test_teacher_class_delete_uses_the_owner_checked_database_transaction(): void
    {
        $this->withoutMiddleware(SupabaseAuth::class);
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->with('classes', '*', [
                'id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
            ])
            ->andReturn([[
                'id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
                'class_name' => 'Section A',
            ]]);
        $supabase->shouldReceive('adminRpcResult')
            ->once()
            ->with('delete_teacher_class', [
                'p_teacher_id' => self::ACTOR_ID,
                'p_class_id' => self::TARGET_ID,
            ])
            ->andReturn([
                'data' => [['deleted_class_id' => self::TARGET_ID]],
                'error' => null,
                'status' => 200,
            ]);
        $supabase->shouldReceive('audit')->once();

        $response = $this->withSession(['supabase_user' => $this->teacher()])
            ->delete('/teacher/classes/' . self::TARGET_ID);

        $response->assertRedirect('/teacher/dashboard?section=classes');
        $response->assertSessionHas('success', 'Class deleted.');
    }

    public function test_teacher_quiz_json_requires_ownership(): void
    {
        $this->withoutMiddleware(SupabaseAuth::class);
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->with('quizzes', '*', [
                'id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
            ])
            ->andReturn([]);

        $response = $this->withSession(['supabase_user' => $this->teacher()])
            ->getJson('/teacher/quizzes/' . self::TARGET_ID);

        $response->assertNotFound()->assertJson([
            'message' => 'Quiz not found.',
        ]);
    }

    public function test_teacher_quiz_delete_scopes_the_mutation_to_its_owner(): void
    {
        $this->withoutMiddleware(SupabaseAuth::class);
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->with('quizzes', '*', [
                'id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
            ])
            ->andReturn([[
                'id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
                'topic' => 'Fractions',
                'visibility' => 'private',
            ]]);
        $supabase->shouldReceive('delete')
            ->once()
            ->with('quizzes', [
                'id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
            ], 'access-token')
            ->andReturnTrue();
        $supabase->shouldReceive('audit')->once();

        $response = $this->withSession([
            'supabase_user' => $this->teacher(),
            'supabase_token' => 'access-token',
        ])
            ->delete('/teacher/quizzes/' . self::TARGET_ID);

        $response->assertRedirect('/teacher/quizzes');
        $response->assertSessionHas('success');
    }

    public function test_starting_a_quiz_rechecks_owner_class_and_waiting_state_in_the_write(): void
    {
        $this->withoutMiddleware(SupabaseAuth::class);
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->with('quiz_sessions', '*', [
                'id' => self::SESSION_ID,
                'class_id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
            ])
            ->andReturn([[
                'id' => self::SESSION_ID,
                'class_id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
                'status' => 'waiting',
                'available_at' => null,
                'due_at' => null,
                'topic' => 'Fractions',
            ]]);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->with('classes', '*', [
                'id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
            ])
            ->andReturn([[
                'id' => self::TARGET_ID,
                'teacher_id' => self::ACTOR_ID,
                'archived_at' => null,
            ]]);
        $supabase->shouldReceive('update')
            ->once()
            ->with(
                'quiz_sessions',
                Mockery::on(fn (array $data): bool => ($data['status'] ?? null) === 'active'
                    && ($data['is_active'] ?? null) === true
                    && isset($data['available_at'], $data['started_at'])),
                [
                    'id' => self::SESSION_ID,
                    'class_id' => self::TARGET_ID,
                    'teacher_id' => self::ACTOR_ID,
                    'status' => 'waiting',
                ],
                'access-token'
            )
            ->andReturn([['id' => self::SESSION_ID]]);
        $supabase->shouldReceive('audit')->once();

        $response = $this->withSession([
            'supabase_user' => $this->teacher(),
            'supabase_token' => 'access-token',
        ])->postJson(
            '/teacher/classes/' . self::TARGET_ID . '/quizzes/' . self::SESSION_ID . '/start'
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_student_cannot_open_a_class_without_membership(): void
    {
        $this->withoutMiddleware(SupabaseAuth::class);
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->with('class_members', 'student_id,joined_at', [
                'class_id' => self::TARGET_ID,
                'student_id' => self::ACTOR_ID,
            ])
            ->andReturn([]);

        $response = $this->withSession(['supabase_user' => $this->student()])
            ->get('/student/classes/' . self::TARGET_ID);

        $response->assertRedirect('/student/dashboard?section=class');
        $response->assertSessionHas('error', 'You do not have access to that class.');
    }

    public function test_notification_action_requires_both_id_and_owner(): void
    {
        $this->withoutMiddleware(SupabaseAuth::class);
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->with('notifications', 'id,action_url,read_at', [
                'id' => self::TARGET_ID,
                'user_id' => self::ACTOR_ID,
            ])
            ->andReturn([]);
        $supabase->shouldNotReceive('adminUpdate');

        $response = $this->withSession(['supabase_user' => $this->student()])
            ->from('/student/dashboard')
            ->post('/notifications/' . self::TARGET_ID . '/read');

        $response->assertRedirect('/student/dashboard');
        $response->assertSessionHas('error', 'That notification is no longer available.');
    }

    public function test_role_middleware_redirects_a_student_away_from_teacher_routes(): void
    {
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->with(
                'profiles',
                'id,role,first_name,last_name,email,avatar_url,grade_level,suspended_at,leaderboard_alias,show_on_leaderboard,auth_sessions_invalid_before',
                ['id' => self::ACTOR_ID]
            )
            ->andReturn([array_merge($this->student(), [
                'suspended_at' => null,
                'auth_sessions_invalid_before' => null,
            ])]);

        $response = $this->withSession([
            'supabase_user' => $this->student(),
            'supabase_authenticated_at' => '2026-09-06T08:00:00+00:00',
        ])->get('/teacher/dashboard');

        $response->assertRedirect('/student/dashboard');
    }

    public function test_an_unsupported_profile_role_invalidates_the_existing_session(): void
    {
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->andReturn([array_merge($this->student(), [
                'role' => 'pending_teacher',
                'suspended_at' => null,
                'auth_sessions_invalid_before' => null,
            ])]);

        $response = $this->withSession([
            'supabase_user' => $this->student(),
            'supabase_token' => 'access-token',
            'supabase_authenticated_at' => '2026-09-06T08:00:00+00:00',
        ])->get('/student/dashboard');

        $response->assertRedirect('/');
        $response->assertSessionMissing('supabase_user');
        $response->assertSessionMissing('supabase_token');
        $response->assertSessionHas(
            'error',
            'Your account is not authorized to use a dashboard. Contact an administrator.'
        );
    }

    public function test_suspension_invalidates_an_existing_application_session(): void
    {
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->andReturn([array_merge($this->student(), [
                'suspended_at' => '2026-09-06T08:30:00+00:00',
                'auth_sessions_invalid_before' => null,
            ])]);

        $response = $this->withSession([
            'supabase_user' => $this->student(),
            'supabase_token' => 'access-token',
            'supabase_authenticated_at' => '2026-09-06T08:00:00+00:00',
        ])->get('/student/dashboard');

        $response->assertRedirect('/');
        $response->assertSessionMissing('supabase_user');
        $response->assertSessionMissing('supabase_token');
    }

    public function test_password_change_invalidates_an_older_application_session(): void
    {
        $supabase = $this->mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelect')
            ->once()
            ->andReturn([array_merge($this->student(), [
                'suspended_at' => null,
                'auth_sessions_invalid_before' => '2026-09-06T08:30:00+00:00',
            ])]);

        $response = $this->withSession([
            'supabase_user' => $this->student(),
            'supabase_token' => 'access-token',
            'supabase_authenticated_at' => '2026-09-06T08:00:00+00:00',
        ])->get('/student/dashboard');

        $response->assertRedirect('/');
        $response->assertSessionMissing('supabase_user');
        $response->assertSessionMissing('supabase_token');
        $response->assertSessionHas('error', 'Your password changed. Please sign in again.');
    }

    /** @return array{id: string, role: string, email: string} */
    private function teacher(): array
    {
        return [
            'id' => self::ACTOR_ID,
            'role' => 'teacher',
            'email' => 'teacher@example.com',
        ];
    }

    /** @return array{id: string, role: string, email: string} */
    private function student(): array
    {
        return [
            'id' => self::ACTOR_ID,
            'role' => 'student',
            'email' => 'student@example.com',
        ];
    }
}
