<?php

namespace Tests\Unit;

use App\Services\SupabaseService;
use App\Services\TeacherLearningHubAnalyticsService;
use Mockery;
use PHPUnit\Framework\TestCase;

class TeacherLearningHubAnalyticsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_maps_curriculum_keys_without_exposing_raw_only_labels(): void
    {
        $database = Mockery::mock(SupabaseService::class);
        $database->shouldReceive('adminRpcResult')->once()->andReturn([
            'error' => null,
            'data' => [[
                'summary' => ['students' => 3],
                'weak_topics' => [['competency_key' => 'g4-equivalent-fractions']],
                'recent_activity' => [],
                'available_classes' => [], 'classes' => [], 'students' => [], 'daily_activity' => [],
            ]],
        ]);

        $state = (new TeacherLearningHubAnalyticsService($database))->dashboard(['id' => 'teacher'], null, 30);
        $this->assertTrue($state['configured']);
        $this->assertSame(3, $state['summary']['students']);
        $this->assertSame('Dissimilar and Equivalent Fractions', $state['weak_topics'][0]['topic_title']);
    }
}
