<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\NotificationDeliveryService;
use App\Services\SystemHealthService;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:deliver {--limit=50}', function () {
    $stats = app(NotificationDeliveryService::class)->deliverPending((int) $this->option('limit'));

    if ($stats['error'] !== null) {
        $this->error($stats['error']);
        return Command::FAILURE;
    }

    $this->info("Claimed {$stats['claimed']}; sent {$stats['sent']}; failed {$stats['failed']}.");
    return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
})->purpose('Deliver queued MathVerse emails and Web Push notifications');

Artisan::command('system:heartbeat', function () {
    if (!app(SystemHealthService::class)->recordSchedulerHeartbeat()) {
        $this->error('Scheduler heartbeat failed.');
        return Command::FAILURE;
    }

    $this->info('Scheduler heartbeat recorded.');
    return Command::SUCCESS;
})->purpose('Record the MathVerse scheduler heartbeat');

Schedule::command('system:heartbeat')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('notifications:deliver --limit=50')
    ->everyMinute()
    ->withoutOverlapping(10);
