<?php

namespace Tests\Unit;

use App\Services\SupabaseService;
use App\Services\SystemHealthService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class SystemHealthQueueTest extends TestCase
{
    private function queueHealth(): array
    {
        $service = new SystemHealthService($this->mock(SupabaseService::class));
        return (new ReflectionMethod($service, 'queueHealth'))->invoke($service);
    }

    public function test_immediate_and_deferred_drivers_do_not_probe_unused_sql_tables(): void
    {
        Schema::shouldReceive('connection')->never();
        DB::shouldReceive('connection')->never();
        Queue::shouldReceive('connection')->never();
        foreach (['sync', 'deferred', 'background'] as $driver) {
            config(['queue.default' => $driver, "queue.connections.{$driver}.driver" => $driver]);
            $state = $this->queueHealth();
            $this->assertSame('healthy', $state['status']);
            $this->assertSame($driver, $state['driver']);
            $this->assertFalse($state['requires_worker']);
            $this->assertNull($state['queued']);
            $this->assertNull($state['failed']);
        }
    }

    public function test_a_configured_database_queue_with_missing_tables_still_warns(): void
    {
        config([
            'queue.default' => 'database', 'queue.connections.database.connection' => 'sqlite',
            'queue.connections.database.table' => 'missing_jobs',
        ]);
        $state = $this->queueHealth();
        $this->assertSame('warning', $state['status']);
        $this->assertTrue($state['requires_worker']);
        $this->assertStringContainsString('jobs table is missing', $state['message']);
    }

    public function test_real_failed_jobs_remain_critical_on_a_named_sql_queue(): void
    {
        config([
            'queue.default' => 'classroom',
            'queue.connections.classroom' => ['driver' => 'database', 'connection' => 'sqlite', 'table' => 'test_jobs'],
            'queue.failed.driver' => 'database-uuids', 'queue.failed.database' => 'sqlite',
            'queue.failed.table' => 'test_failed_jobs',
        ]);
        foreach (['test_jobs', 'test_failed_jobs'] as $table) {
            Schema::connection('sqlite')->create($table, fn (Blueprint $schema) => $schema->id());
            DB::connection('sqlite')->table($table)->insert(['id' => 1]);
        }
        $state = $this->queueHealth();
        $this->assertSame('critical', $state['status']);
        $this->assertSame('classroom', $state['connection']);
        $this->assertSame(1, $state['queued']);
        $this->assertSame(1, $state['failed']);
    }

    public function test_non_sql_queue_uses_its_own_connection_and_queue(): void
    {
        config([
            'queue.default' => 'classroom',
            'queue.connections.classroom' => ['driver' => 'redis', 'queue' => 'practice'],
            'queue.failed.driver' => 'null',
        ]);
        Schema::shouldReceive('connection')->never();
        Queue::shouldReceive('connection')->once()->with('classroom')->andReturnSelf();
        Queue::shouldReceive('size')->once()->with('practice')->andReturn(101);
        $state = $this->queueHealth();
        $this->assertSame('warning', $state['status']);
        $this->assertSame(101, $state['queued']);
        $this->assertNull($state['failed']);
    }
}
