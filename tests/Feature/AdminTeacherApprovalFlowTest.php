<?php

namespace Tests\Feature;

use App\Services\NotificationDeliveryService;
use App\Services\SupabaseService;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminTeacherApprovalFlowTest extends TestCase
{
    private const TEACHER_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function test_approval_verifies_that_its_email_was_queued(): void
    {
        $this->withoutMiddleware();
        $profile = $this->pendingTeacher();

        $this->mock(SupabaseService::class, function (MockInterface $mock) use ($profile): void {
            $mock->shouldReceive('adminSelect')
                ->once()
                ->with(
                    'profiles',
                    'id,role,first_name,last_name,email',
                    ['id' => self::TEACHER_ID]
                )
                ->andReturn([$profile]);
            $mock->shouldReceive('adminUpdate')
                ->once()
                ->with(
                    'profiles',
                    ['role' => 'teacher'],
                    ['id' => self::TEACHER_ID, 'role' => 'pending_teacher']
                )
                ->andReturn([['id' => self::TEACHER_ID]]);
            $mock->shouldReceive('audit')->once()->andReturn(true);
        });
        $this->mock(NotificationDeliveryService::class, function (MockInterface $mock) use ($profile): void {
            $mock->shouldReceive('isReady')->once()->andReturn(true);
            $mock->shouldReceive('emailConfigurationIssue')->once()->andReturnNull();
            $mock->shouldReceive('ensureTeacherApprovalEmailQueued')
                ->once()
                ->with($profile)
                ->andReturn(true);
        });

        $response = $this->withSession([
            'supabase_user' => ['id' => 'admin-id', 'role' => 'admin'],
        ])->post('/admin/approve-teacher/' . self::TEACHER_ID);

        $response->assertRedirect('/admin/dashboard?section=role-verify');
        $response->assertSessionHas(
            'success',
            'Teacher approved. The approval email is queued for delivery.'
        );
    }

    public function test_approval_is_stopped_when_production_email_is_unavailable(): void
    {
        $this->withoutMiddleware();
        $profile = $this->pendingTeacher();

        $this->mock(SupabaseService::class, function (MockInterface $mock) use ($profile): void {
            $mock->shouldReceive('adminSelect')->once()->andReturn([$profile]);
            $mock->shouldNotReceive('adminUpdate');
        });
        $this->mock(NotificationDeliveryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isReady')->once()->andReturn(true);
            $mock->shouldReceive('emailConfigurationIssue')
                ->once()
                ->andReturn('MathVerse event email is not connected to a real mail provider.');
        });

        $response = $this->post('/admin/approve-teacher/' . self::TEACHER_ID);

        $response->assertRedirect('/admin/dashboard?section=role-verify');
        $response->assertSessionHas(
            'error',
            'The teacher was not approved because the decision email is unavailable. MathVerse event email is not connected to a real mail provider.'
        );
    }

    public function test_an_active_teacher_can_have_the_approval_email_queued_again(): void
    {
        $this->withoutMiddleware();
        $profile = array_merge($this->pendingTeacher(), [
            'role' => 'teacher',
            'suspended_at' => null,
        ]);

        $this->mock(SupabaseService::class, function (MockInterface $mock) use ($profile): void {
            $mock->shouldReceive('adminSelect')->once()->andReturn([$profile]);
            $mock->shouldReceive('audit')
                ->once()
                ->withArgs(fn ($actor, $action, $targetType, $targetId, $metadata): bool =>
                    $action === 'teacher.approval_email_requeued'
                    && $targetType === 'profile'
                    && $targetId === self::TEACHER_ID
                    && $metadata === ['queued' => true]
                )
                ->andReturn(true);
        });
        $this->mock(NotificationDeliveryService::class, function (MockInterface $mock) use ($profile): void {
            $mock->shouldReceive('isReady')->once()->andReturn(true);
            $mock->shouldReceive('emailConfigurationIssue')->once()->andReturnNull();
            $mock->shouldReceive('queueTeacherApprovalEmail')
                ->once()
                ->withArgs(fn (array $recipient, string $key): bool =>
                    $recipient === $profile
                    && str_starts_with($key, 'teacher-approved-resend:' . self::TEACHER_ID . ':')
                )
                ->andReturn(true);
        });

        $response = $this->withSession([
            'supabase_user' => ['id' => 'admin-id', 'role' => 'admin'],
        ])->post('/admin/teachers/' . self::TEACHER_ID . '/approval-email');

        $response->assertRedirect('/admin/dashboard?section=teachers');
        $response->assertSessionHas(
            'success',
            'The teacher approval email is queued. Duplicate requests are limited to one per hour.'
        );
    }

    /** @return array<string, string> */
    private function pendingTeacher(): array
    {
        return [
            'id' => self::TEACHER_ID,
            'role' => 'pending_teacher',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
        ];
    }
}
