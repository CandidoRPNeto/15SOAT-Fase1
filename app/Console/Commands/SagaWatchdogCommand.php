<?php

namespace App\Console\Commands;

use App\Application\Saga\SagaWatchdog;
use Illuminate\Console\Command;

/**
 * Scheduled every minute (routes/console.php). See SagaWatchdog.
 *
 * Example: php artisan saga:watchdog
 */
class SagaWatchdogCommand extends Command
{
    protected $signature = 'saga:watchdog';

    protected $description = 'Re-send or compensate saga steps whose reply timed out';

    public function handle(SagaWatchdog $watchdog): int
    {
        $result = $watchdog->run(
            (int) config('messaging.saga.step_timeout_seconds'),
            (int) config('messaging.saga.max_attempts'),
        );
        $this->info("resent={$result['resent']} compensated={$result['compensated']}");

        return self::SUCCESS;
    }
}
