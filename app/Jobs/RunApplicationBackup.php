<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class RunApplicationBackup implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $exitCode = Artisan::call('backup:run', ['--disable-notifications' => true]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Application backup failed: '.Artisan::output());
        }
    }
}
