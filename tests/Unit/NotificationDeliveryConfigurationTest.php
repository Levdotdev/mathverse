<?php

namespace Tests\Unit;

use App\Mail\MathVerseEventMail;
use App\Services\AdminPushService;
use App\Services\NotificationDeliveryService;
use App\Services\SupabaseService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class NotificationDeliveryConfigurationTest extends TestCase
{
    public function test_production_rejects_a_log_only_mailer(): void
    {
        $this->useProductionEnvironment();
        config([
            'mail.default' => 'log',
            'mail.from.address' => 'notifications@mathmetaverse.space',
        ]);

        $this->assertNotNull($this->service()->emailConfigurationIssue());
    }

    public function test_production_accepts_an_external_smtp_mailer(): void
    {
        $this->useProductionEnvironment();
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'notifications@mathmetaverse.space',
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => 'smtp.mail-provider.test',
                'port' => 587,
            ],
        ]);

        $this->assertNull($this->service()->emailConfigurationIssue());
    }

    public function test_production_rejects_a_failover_that_can_silently_log_mail(): void
    {
        $this->useProductionEnvironment();
        config([
            'mail.default' => 'failover',
            'mail.from.address' => 'notifications@mathmetaverse.space',
            'mail.mailers.failover' => [
                'transport' => 'failover',
                'mailers' => ['smtp', 'log'],
            ],
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => 'smtp.mail-provider.test',
            ],
            'mail.mailers.log' => ['transport' => 'log'],
        ]);

        $this->assertNotNull($this->service()->emailConfigurationIssue());
    }

    public function test_teacher_approval_email_is_claimed_and_sent_immediately(): void
    {
        Mail::fake();
        config(['app.url' => 'https://mathmetaverse.space']);

        $profile = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
        ];
        $delivery = [
            'id' => '660e8400-e29b-41d4-a716-446655440000',
            'notification_id' => '770e8400-e29b-41d4-a716-446655440000',
            'user_id' => $profile['id'],
            'channel' => 'email',
            'event_type' => 'teacher_approved',
            'recipient_email' => $profile['email'],
            'recipient_name' => 'Ada Lovelace',
            'title' => 'Teacher account approved',
            'message' => 'Your MathVerse teacher application was approved.',
            'action_url' => '/teacher/dashboard',
            'data' => [],
            'delivery_key' => 'notification:approval:email',
            'status' => 'pending',
            'attempts' => 0,
        ];

        $supabase = Mockery::mock(SupabaseService::class);
        $supabase->shouldReceive('adminSelectResult')
            ->once()
            ->withArgs(fn (string $table, string $columns, array $filters): bool =>
                $table === 'notification_deliveries'
                && $columns === 'id,status'
                && $filters['user_id'] === $profile['id']
            )
            ->andReturn([
                'data' => [['id' => $delivery['id'], 'status' => 'pending']],
                'error' => null,
                'status' => 200,
            ]);
        $supabase->shouldReceive('adminSelectResult')
            ->once()
            ->withArgs(fn (string $table, string $columns, array $filters): bool =>
                $table === 'notification_deliveries'
                && str_contains($columns, 'recipient_email')
                && $filters['user_id'] === $profile['id']
            )
            ->andReturn(['data' => [$delivery], 'error' => null, 'status' => 200]);
        $supabase->shouldReceive('adminUpdate')
            ->once()
            ->withArgs(fn (string $table, array $data, array $filters): bool =>
                $table === 'notification_deliveries'
                && ($data['status'] ?? null) === 'sending'
                && ($data['attempts'] ?? null) === 1
                && ($filters['id'] ?? null) === $delivery['id']
            )
            ->andReturnUsing(fn (string $table, array $data): array => [array_merge($delivery, $data)]);
        $supabase->shouldReceive('adminUpdate')
            ->once()
            ->withArgs(fn (string $table, array $data, array $filters): bool =>
                $table === 'notification_deliveries'
                && ($data['status'] ?? null) === 'sent'
                && ($filters['id'] ?? null) === $delivery['id']
                && !empty($filters['locked_by'])
            )
            ->andReturn([['id' => $delivery['id']]]);

        $service = new NotificationDeliveryService(
            $supabase,
            Mockery::mock(AdminPushService::class),
        );

        $this->assertSame(
            ['sent' => true, 'queued' => true],
            $service->deliverTeacherApprovalEmailNow($profile)
        );
        Mail::assertSent(
            MathVerseEventMail::class,
            fn (MathVerseEventMail $mail): bool => $mail->hasTo($profile['email'])
        );
    }

    public function test_legacy_quiz_assignment_email_rows_are_delivered_as_web_push(): void
    {
        Mail::fake();
        $delivery = [
            'id' => '880e8400-e29b-41d4-a716-446655440000',
            'notification_id' => '990e8400-e29b-41d4-a716-446655440000',
            'user_id' => '550e8400-e29b-41d4-a716-446655440000',
            'channel' => 'email',
            'event_type' => 'quiz_assigned',
            'recipient_email' => 'student@example.test',
            'title' => 'New quiz assigned',
            'message' => 'A new quiz is waiting for you.',
            'action_url' => '/student/classes/770e8400-e29b-41d4-a716-446655440000',
            'status' => 'sending',
            'attempts' => 1,
        ];

        $supabase = Mockery::mock(SupabaseService::class);
        $supabase->shouldReceive('adminRpc')->twice()->andReturn([]);
        $supabase->shouldReceive('adminRpcResult')
            ->once()
            ->withArgs(fn (string $function, array $arguments): bool =>
                $function === 'claim_notification_deliveries'
                && ($arguments['p_limit'] ?? null) === 50
            )
            ->andReturn(['data' => [$delivery], 'error' => null, 'status' => 200]);
        $supabase->shouldReceive('adminUpdate')
            ->once()
            ->withArgs(fn (string $table, array $data, array $filters): bool =>
                $table === 'notification_deliveries'
                && ($data['status'] ?? null) === 'sent'
                && ($filters['id'] ?? null) === $delivery['id']
            )
            ->andReturn([['id' => $delivery['id']]]);

        $webPush = Mockery::mock(AdminPushService::class);
        $webPush->shouldReceive('sendToUser')
            ->once()
            ->withArgs(fn (string $userId, string $title, string $message, string $path, string $tag): bool =>
                $userId === $delivery['user_id']
                && $title === $delivery['title']
                && $message === $delivery['message']
                && $path === $delivery['action_url']
                && str_contains($tag, 'quiz-assigned')
            )
            ->andReturn(true);

        $service = new NotificationDeliveryService($supabase, $webPush);

        $this->assertSame(
            ['claimed' => 1, 'sent' => 1, 'failed' => 0, 'error' => null],
            $service->deliverPending()
        );
        Mail::assertNothingSent();
    }

    private function useProductionEnvironment(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
    }

    private function service(): NotificationDeliveryService
    {
        return new NotificationDeliveryService(
            Mockery::mock(SupabaseService::class),
            Mockery::mock(AdminPushService::class),
        );
    }
}
