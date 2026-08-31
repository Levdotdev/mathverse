<?php

namespace App\Services;

use App\Mail\MathVerseEventMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationDeliveryService
{
    public function __construct(
        private SupabaseService $supabase,
        private AdminPushService $webPush,
    ) {}

    public function isReady(): bool
    {
        return $this->supabase->adminSelectResult(
            'notification_deliveries',
            'id',
            ['limit' => 1]
        )['error'] === null;
    }

    public function queueStandaloneEmail(
        string $eventType,
        string $recipientEmail,
        string $recipientName,
        string $title,
        string $message,
        ?string $actionUrl,
        array $data,
        string $deliveryKey,
    ): bool {
        $queued = $this->supabase->adminInsertResult('notification_deliveries', [
            'channel' => 'email',
            'event_type' => $eventType,
            'recipient_email' => mb_strtolower(trim($recipientEmail)),
            'recipient_name' => trim($recipientName),
            'title' => $title,
            'message' => $message,
            'action_url' => $this->safeActionPath($actionUrl),
            'data' => $data,
            'delivery_key' => $deliveryKey,
        ]);

        if (isset($queued['data'][0]['id'])) {
            return true;
        }

        // A repeated administrator request is already safely queued when the
        // same idempotency key exists.
        return $this->supabase->adminCount('notification_deliveries', [
            'delivery_key' => $deliveryKey,
        ]) > 0;
    }

    /** @return array{claimed: int, sent: int, failed: int, error: string|null} */
    public function deliverPending(int $limit = 50): array
    {
        // Scheduled quiz state changes must happen even when nobody is browsing
        // the site, otherwise a "quiz available" email could be delayed until
        // the next page request.
        $this->supabase->adminRpc('advance_quiz_session_schedule');
        $this->supabase->adminRpc('generate_upcoming_quiz_notifications', [
            'p_user_id' => null,
        ]);

        $workerId = (string) Str::uuid();
        $claimed = $this->supabase->adminRpcResult('claim_notification_deliveries', [
            'p_limit' => max(1, min($limit, 100)),
            'p_worker_id' => $workerId,
        ]);

        if ($claimed['error'] !== null) {
            return [
                'claimed' => 0,
                'sent' => 0,
                'failed' => 0,
                'error' => $claimed['error'],
            ];
        }

        $sent = 0;
        $failed = 0;
        foreach ($claimed['data'] as $delivery) {
            try {
                $this->deliver($delivery);
                $this->markSent($delivery, $workerId);
                $sent++;
            } catch (\Throwable $exception) {
                $this->markFailed($delivery, $workerId, $exception->getMessage());
                Log::warning('MathVerse notification delivery failed.', [
                    'delivery_id' => $delivery['id'] ?? null,
                    'event_type' => $delivery['event_type'] ?? null,
                    'channel' => $delivery['channel'] ?? null,
                    'attempts' => $delivery['attempts'] ?? null,
                    'message' => $exception->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'claimed' => count($claimed['data']),
            'sent' => $sent,
            'failed' => $failed,
            'error' => null,
        ];
    }

    private function deliver(array $delivery): void
    {
        if (($delivery['channel'] ?? '') === 'email') {
            $this->deliverEmail($delivery);
            return;
        }

        if (($delivery['channel'] ?? '') === 'web_push') {
            $userId = (string) ($delivery['user_id'] ?? '');
            if ($userId === '') {
                throw new \RuntimeException('The Web Push recipient is missing.');
            }

            $sent = $this->webPush->sendToUser(
                $userId,
                (string) ($delivery['title'] ?? 'MathVerse Notification'),
                (string) ($delivery['message'] ?? 'A new item needs your attention.'),
                $this->safeActionPath($delivery['action_url'] ?? null) ?? '/',
                'mathverse-' . Str::slug((string) ($delivery['event_type'] ?? 'notification'))
                    . '-' . (string) ($delivery['notification_id'] ?? $delivery['id'])
            );
            if (!$sent) {
                throw new \RuntimeException('The Web Push service rejected the delivery.');
            }
            return;
        }

        throw new \RuntimeException('The notification delivery channel is invalid.');
    }

    private function deliverEmail(array $delivery): void
    {
        if (app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true)) {
            throw new \RuntimeException('Production email delivery is not configured with a real mail transport.');
        }

        $email = mb_strtolower(trim((string) ($delivery['recipient_email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('The email recipient is invalid.');
        }

        $presentation = $this->emailPresentation((string) ($delivery['event_type'] ?? ''));
        $actionPath = $this->safeActionPath($delivery['action_url'] ?? null);
        $baseUrl = rtrim((string) config('app.url'), '/');
        if ($actionPath !== null && filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('APP_URL must be the deployed MathVerse root URL before email delivery.');
        }
        $actionUrl = $actionPath === null ? null : $baseUrl . $actionPath;

        Mail::to($email)->send(new MathVerseEventMail(
            subjectLine: '[Math MetaVerse] ' . (string) $delivery['title'],
            recipientName: trim((string) ($delivery['recipient_name'] ?? '')),
            heading: (string) $delivery['title'],
            messageText: (string) $delivery['message'],
            actionLabel: $actionUrl === null ? null : $presentation['action_label'],
            actionUrl: $actionUrl,
            eyebrow: $presentation['eyebrow'],
            accentColor: $presentation['accent'],
            securityNote: $presentation['note'],
            details: $this->emailDetails($delivery),
        ));
    }

    /** @return array{action_label: string, eyebrow: string, accent: string, note: string} */
    private function emailPresentation(string $eventType): array
    {
        return match ($eventType) {
            'teacher_application_received' => [
                'action_label' => 'Open MathVerse',
                'eyebrow' => 'Teacher Registration',
                'accent' => '#22d3ee',
                'note' => 'An administrator will review the application. No additional submission is needed.',
            ],
            'teacher_approved' => [
                'action_label' => 'Open Teacher Dashboard',
                'eyebrow' => 'Application Decision',
                'accent' => '#22c55e',
                'note' => 'You can now sign in with the email address and password used during registration.',
            ],
            'teacher_denied' => [
                'action_label' => 'Return to MathVerse',
                'eyebrow' => 'Application Decision',
                'accent' => '#f97316',
                'note' => 'This message confirms the administrator’s decision on the submitted teacher application.',
            ],
            'account_suspended' => [
                'action_label' => 'Open MathVerse',
                'eyebrow' => 'Account Status',
                'accent' => '#ef4444',
                'note' => 'Contact a MathVerse administrator if you believe this action was made in error.',
            ],
            'account_restored' => [
                'action_label' => 'Sign In to MathVerse',
                'eyebrow' => 'Account Status',
                'accent' => '#22c55e',
                'note' => 'Your existing sign-in credentials can be used again.',
            ],
            'quiz_assigned' => [
                'action_label' => 'View Assigned Quiz',
                'eyebrow' => 'New Assignment',
                'accent' => '#a855f7',
                'note' => 'Check the availability and due times in MathVerse before beginning.',
            ],
            'quiz_started' => [
                'action_label' => 'Open Available Quiz',
                'eyebrow' => 'Quiz Available',
                'accent' => '#22c55e',
                'note' => 'The quiz is available now. Submit it before its due time, if one is set.',
            ],
            'quiz_retake_granted' => [
                'action_label' => 'Open Retake',
                'eyebrow' => 'Retake Authorized',
                'accent' => '#06b6d4',
                'note' => 'Only the teacher-authorized additional attempt is available.',
            ],
            'quiz_excused' => [
                'action_label' => 'View Class',
                'eyebrow' => 'Absence Excused',
                'accent' => '#8b5cf6',
                'note' => 'This quiz will not be counted as a missed assignment for you.',
            ],
            'quiz_result_recorded' => [
                'action_label' => 'View Quiz Result',
                'eyebrow' => 'Submission Receipt',
                'accent' => '#22d3ee',
                'note' => 'One receipt is sent for the initial attempt and for each separately teacher-authorized retake.',
            ],
            'removed_from_class' => [
                'action_label' => 'Open Student Dashboard',
                'eyebrow' => 'Class Membership',
                'accent' => '#ef4444',
                'note' => 'Contact the teacher or a MathVerse administrator if this was unexpected.',
            ],
            default => [
                'action_label' => 'Open MathVerse',
                'eyebrow' => 'Account Notification',
                'accent' => '#22d3ee',
                'note' => 'This automated message was sent by MathVerse.',
            ],
        };
    }

    /** @return array<int, array{label: string, value: string}> */
    private function emailDetails(array $delivery): array
    {
        $data = is_array($delivery['data'] ?? null) ? $delivery['data'] : [];
        $details = [];

        if (($delivery['event_type'] ?? '') === 'quiz_result_recorded') {
            $correct = isset($data['correct_answers']) ? (int) $data['correct_answers'] : null;
            $total = isset($data['total_questions']) ? (int) $data['total_questions'] : null;
            $attempt = max(1, (int) ($data['attempt_number'] ?? 1));
            if ($correct !== null && $total !== null) {
                $details[] = ['label' => 'Recorded score', 'value' => "{$correct} of {$total} correct"];
            }
            $details[] = [
                'label' => 'Attempt',
                'value' => $attempt === 1
                    ? 'Initial attempt'
                    : 'Authorized retake ' . ($attempt - 1) . " (attempt {$attempt})",
            ];
        }

        foreach ([
            'available_at' => 'Available',
            'due_at' => 'Due',
            'retake_due_at' => 'Retake due',
        ] as $key => $label) {
            if (!empty($data[$key])) {
                $details[] = ['label' => $label, 'value' => $this->formatDateTime((string) $data[$key])];
            }
        }

        if (!empty($data['reason'])
            && in_array($delivery['event_type'] ?? '', ['account_suspended', 'quiz_excused'], true)) {
            $details[] = ['label' => 'Reason', 'value' => mb_substr(trim((string) $data['reason']), 0, 500)];
        }

        return $details;
    }

    private function formatDateTime(string $value): string
    {
        try {
            return Carbon::parse($value)
                ->timezone((string) config('app.timezone'))
                ->format('M d, Y · h:i A T');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function markSent(array $delivery, string $workerId): void
    {
        $this->supabase->adminUpdate('notification_deliveries', [
            'status' => 'sent',
            'delivered_at' => now()->toIso8601String(),
            'last_error' => null,
            'locked_at' => null,
            'locked_by' => null,
            'updated_at' => now()->toIso8601String(),
        ], [
            'id' => $delivery['id'],
            'locked_by' => $workerId,
        ]);
    }

    private function markFailed(array $delivery, string $workerId, string $message): void
    {
        $attempts = (int) ($delivery['attempts'] ?? 1);
        $delayMinutes = match (true) {
            $attempts <= 1 => 5,
            $attempts === 2 => 15,
            $attempts === 3 => 60,
            default => 180,
        };

        $this->supabase->adminUpdate('notification_deliveries', [
            'status' => 'failed',
            'available_at' => now()->addMinutes($delayMinutes)->toIso8601String(),
            'last_error' => mb_substr($message, 0, 1000),
            'locked_at' => null,
            'locked_by' => null,
            'updated_at' => now()->toIso8601String(),
        ], [
            'id' => $delivery['id'],
            'locked_by' => $workerId,
            'attempts' => $attempts,
        ]);
    }

    private function safeActionPath(mixed $value): ?string
    {
        $path = trim((string) ($value ?? ''));

        return preg_match('#^/(?!/)#', $path) === 1 ? $path : null;
    }
}
