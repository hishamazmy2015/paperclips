<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ListingFeed;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Every 30 minutes from the scheduler: queue a SyncFeed for each active feed that is due. */
final class SyncDueFeeds implements ShouldQueue
{
    use Queueable;

    public function handle(): int
    {
        $due = TenantContext::global(fn () => ListingFeed::unscopedByTenant()
            ->where('active', true)
            ->where(function ($query): void {
                $query->whereNull('last_sync_at')
                    ->orWhereRaw("last_sync_at + (schedule_minutes || ' minutes')::interval <= ?", [now()]);
            })
            ->orderBy('id')
            ->limit(500)
            ->pluck('id'));

        foreach ($due as $id) {
            SyncFeed::dispatch((int) $id);
        }

        return $due->count();
    }
}
