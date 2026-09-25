<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Platform\EventPartitions;

/** platform:events:partitions — create the coming months' partitions of the events table (spec §8). */
final class EventPartitionsCommand extends PlatformCommand
{
    protected $signature = 'platform:events:partitions {--months-ahead=2}';

    protected $description = 'Ensure monthly partitions of the events table exist for the coming months';

    public function handle(): int
    {
        $created = EventPartitions::ensureMonths(now(), max(0, (int) $this->option('months-ahead')));

        return $this->emit(['ok' => true, 'created' => $created], $created === [] ? 'All partitions already exist' : 'Created: '.implode(', ', $created));
    }
}
