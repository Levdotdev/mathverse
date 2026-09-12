<?php

namespace App\Services;

use App\Support\PracticeCurriculum;

class TeacherLearningHubAnalyticsService
{
    public function __construct(private SupabaseService $supabase) {}

    public function dashboard(array $teacher, ?string $classId = null, int $days = 30): array
    {
        $days = in_array($days, [7, 30, 90, 180], true) ? $days : 30;
        $result = $this->supabase->adminRpcResult('teacher_learning_hub_analytics', [
            'p_teacher_id' => (string) ($teacher['id'] ?? ''),
            'p_class_id' => $classId,
            'p_days' => $days,
        ]);

        if ($result['error'] !== null || !is_array($result['data'][0] ?? null)) {
            return $this->emptyState($days, $classId, false);
        }

        $payload = $result['data'][0];
        $topics = $this->topicMap();
        foreach (['weak_topics', 'recent_activity'] as $collection) {
            $payload[$collection] = is_array($payload[$collection] ?? null) ? $payload[$collection] : [];
            foreach ($payload[$collection] as &$row) {
                if (!is_array($row)) { $row = []; continue; }
                $key = (string) ($row['competency_key'] ?? '');
                $row['topic_title'] = $topics[$key]['title'] ?? $this->humanizeKey($key);
                $row['topic_icon'] = $topics[$key]['icon'] ?? 'fa-book-open';
            }
            unset($row);
        }

        $state = $this->emptyState($days, $classId, true);
        if (is_array($payload['summary'] ?? null)) {
            $state['summary'] = array_replace($state['summary'], $payload['summary']);
        }
        foreach (['available_classes', 'classes', 'students', 'weak_topics', 'daily_activity', 'recent_activity'] as $key) {
            if (is_array($payload[$key] ?? null)) $state[$key] = $payload[$key];
        }
        $state['generated_at'] = isset($payload['generated_at']) ? (string) $payload['generated_at'] : null;

        return $state;
    }

    private function topicMap(): array
    {
        $topics = [];
        for ($grade = 1; $grade <= 6; $grade++) {
            foreach (PracticeCurriculum::forGrade($grade) as $topic) $topics[$topic['key']] = $topic;
        }
        return $topics;
    }

    private function humanizeKey(string $key): string
    {
        $label = preg_replace('/^g[1-6]-/', '', $key) ?? $key;
        return ucwords(str_replace('-', ' ', $label ?: 'Unknown topic'));
    }

    private function emptyState(int $days, ?string $classId, bool $configured): array
    {
        return [
            'configured' => $configured,
            'message' => $configured ? null : 'The Learning Hub analytics database update has not been installed yet.',
            'period_days' => $days,
            'selected_class_id' => $classId,
            'generated_at' => null,
            'summary' => [
                'students' => 0, 'active_students' => 0, 'answers' => 0,
                'accuracy' => 0, 'average_mastery' => 0, 'hints_used' => 0,
                'hint_usage_rate' => 0, 'improvement' => 0,
            ],
            'available_classes' => [], 'classes' => [], 'students' => [],
            'weak_topics' => [], 'daily_activity' => [], 'recent_activity' => [],
        ];
    }
}
