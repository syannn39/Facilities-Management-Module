<?php

namespace App\Console\Commands;

use App\Services\CheckInService;
use Illuminate\Console\Command;

/**
 * Algorithm 4: No-show Detection.
 *
 * Run via `php artisan bookings:detect-no-shows`, scheduled in
 * routes/console.php (or App\Console\Kernel on Laravel <11) to run every minute.
 */
class DetectNoShows extends Command
{
    protected $signature = 'bookings:detect-no-shows';

    protected $description = 'Cancel confirmed bookings whose grace-period check-in window has elapsed and release the slot.';

    public function handle(CheckInService $checkInService): int
    {
        $cancelled = $checkInService->detectNoShows();

        $this->info("No-show sweep complete. {$cancelled} booking(s) cancelled.");

        return self::SUCCESS;
    }
}
