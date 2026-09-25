<?php

declare(strict_types=1);

namespace App\Platform;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Monthly partitions for the events table (spec §8). Idempotent: existing partitions are kept.
 */
final class EventPartitions
{
    public static function nameFor(CarbonInterface $month): string
    {
        return 'events_'.$month->format('Y_m');
    }

    /** Create the partition holding $month if it does not exist yet. Returns true when created. */
    public static function ensure(CarbonInterface $month): bool
    {
        $start = $month->copy()->startOfMonth();
        $end = $start->copy()->addMonth();
        $name = self::nameFor($start);

        $exists = DB::selectOne('SELECT to_regclass(?) AS oid', [$name]);
        if ($exists !== null && $exists->oid !== null) {
            return false;
        }

        DB::statement(sprintf(
            "CREATE TABLE %s PARTITION OF events FOR VALUES FROM ('%s') TO ('%s')",
            $name,
            $start->toDateString(),
            $end->toDateString(),
        ));

        return true;
    }

    /**
     * Ensure the partitions for $from and the following $monthsAhead months.
     *
     * @return list<string> names of the partitions created
     */
    public static function ensureMonths(CarbonInterface $from, int $monthsAhead): array
    {
        $created = [];
        for ($i = 0; $i <= $monthsAhead; $i++) {
            $month = $from->copy()->startOfMonth()->addMonths($i);
            if (self::ensure($month)) {
                $created[] = self::nameFor($month);
            }
        }

        return $created;
    }
}
