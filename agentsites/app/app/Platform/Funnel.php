<?php

declare(strict_types=1);

namespace App\Platform;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The onboarding funnel from the events table (spec §13): views and completions per step,
 * drop-off, median seconds per step, published count — split by device and locale.
 */
final class Funnel
{
    public const STEPS = 3;

    /**
     * @return array{
     *   from: string, to: string, started: int, signed_up: int, published: int,
     *   completion_rate: float,
     *   steps: list<array{n: int, viewed: int, completed: int, drop_off: float, median_seconds: float|null}>,
     *   by_device: array<string, array{started: int, published: int}>,
     *   by_locale: array<string, array{started: int, published: int}>
     * }
     */
    public function report(CarbonInterface $from, CarbonInterface $to): array
    {
        /** @var Collection<int, Event> $events */
        $events = Event::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('name', ['onboarding.started', 'account.created', 'step.viewed', 'step.completed', 'published'])
            ->orderBy('created_at')
            ->get(['name', 'account_id', 'session_id', 'properties', 'device', 'locale', 'created_at']);

        $started = $events->where('name', 'onboarding.started');
        $signedUp = $events->where('name', 'account.created');
        $published = $events->where('name', 'published')->filter(fn (Event $e): bool => (bool) data_get($e->properties, 'first', true));

        $steps = [];
        for ($n = 1; $n <= self::STEPS; $n++) {
            $viewed = $events->filter(fn (Event $e): bool => $e->name === 'step.viewed' && (int) data_get($e->properties, 'n') === $n);
            $completed = $events->filter(fn (Event $e): bool => $e->name === 'step.completed' && (int) data_get($e->properties, 'n') === $n);
            $viewedAccounts = $viewed->pluck('account_id')->filter()->unique()->count();
            $completedAccounts = $completed->pluck('account_id')->filter()->unique()->count();

            $durations = [];
            foreach ($completed->groupBy('account_id') as $accountId => $completions) {
                $firstView = $viewed->where('account_id', $accountId)->min('created_at');
                $firstCompletion = $completions->min('created_at');
                if ($firstView !== null && $firstCompletion !== null && $firstCompletion >= $firstView) {
                    $durations[] = $firstView->diffInSeconds($firstCompletion);
                }
            }

            $steps[] = [
                'n' => $n,
                'viewed' => $viewedAccounts,
                'completed' => $completedAccounts,
                'drop_off' => $viewedAccounts > 0 ? round(1 - $completedAccounts / $viewedAccounts, 3) : 0.0,
                'median_seconds' => self::median($durations),
            ];
        }

        $split = static function (Collection $started, Collection $published, string $key): array {
            $out = [];
            foreach ($started->groupBy($key) as $value => $group) {
                $out[(string) ($value ?: 'unknown')] = ['started' => $group->count(), 'published' => 0];
            }
            foreach ($published->groupBy($key) as $value => $group) {
                $out[(string) ($value ?: 'unknown')] ??= ['started' => 0, 'published' => 0];
                $out[(string) ($value ?: 'unknown')]['published'] = $group->count();
            }
            ksort($out);

            return $out;
        };

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'started' => $started->pluck('session_id')->filter()->unique()->count() ?: $started->count(),
            'signed_up' => $signedUp->count(),
            'published' => $published->count(),
            'completion_rate' => $signedUp->count() > 0 ? round($published->count() / $signedUp->count(), 3) : 0.0,
            'steps' => $steps,
            'by_device' => $split($started, $published, 'device'),
            'by_locale' => $split($started, $published, 'locale'),
        ];
    }

    /** @param  list<int|float>  $values */
    public static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
