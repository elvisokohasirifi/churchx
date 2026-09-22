<?php

use App\BroadcastStatus;
use App\Jobs\DeliverBroadcast;
use App\Models\Broadcast;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Broadcast::query()->where('status', BroadcastStatus::Scheduled)->where('scheduled_at', '<=', now())->each(function (Broadcast $broadcast): void {
        if ($broadcast->update(['status' => BroadcastStatus::Processing])) {
            DeliverBroadcast::dispatch($broadcast);
        }
    });
})->name('dispatch-scheduled-broadcasts')->everyMinute()->withoutOverlapping();

Schedule::command('backup:run --disable-notifications')
    ->dailyAt('02:00')
    ->withoutOverlapping(180);

Schedule::command('backup:clean --disable-notifications')
    ->dailyAt('03:00')
    ->withoutOverlapping(180);

Schedule::command('activitylog:clean --force')
    ->dailyAt('03:30')
    ->withoutOverlapping();
