<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Platform\Funnel;

/** platform:funnel — the onboarding funnel numbers (spec §13); the admin dashboard (Phase 6) reads the same report. */
final class FunnelCommand extends PlatformCommand
{
    protected $signature = 'platform:funnel {--days=7 : Window in days}';

    protected $description = 'Onboarding funnel: starts, sign-ups, step views/completions, drop-off, median seconds, published';

    public function handle(Funnel $funnel): int
    {
        $days = max(1, (int) $this->option('days'));
        $report = $funnel->report(now()->subDays($days), now());

        if ((bool) $this->option('json')) {
            return $this->emit($report);
        }

        $this->info(sprintf('Onboarding funnel, last %d day(s): %d started, %d signed up, %d published (%.1f%% of sign-ups)', $days, $report['started'], $report['signed_up'], $report['published'], $report['completion_rate'] * 100));
        $this->table(['Step', 'Viewed', 'Completed', 'Drop-off', 'Median s'], array_map(static fn (array $step): array => [
            $step['n'], $step['viewed'], $step['completed'], sprintf('%.1f%%', $step['drop_off'] * 100), $step['median_seconds'] === null ? '—' : (string) $step['median_seconds'],
        ], $report['steps']));
        foreach (['by_device' => 'Device', 'by_locale' => 'Locale'] as $key => $label) {
            $rows = [];
            foreach ($report[$key] as $value => $counts) {
                $rows[] = [$value, $counts['started'], $counts['published']];
            }
            $this->table([$label, 'Started', 'Published'], $rows);
        }

        return self::SUCCESS;
    }
}
