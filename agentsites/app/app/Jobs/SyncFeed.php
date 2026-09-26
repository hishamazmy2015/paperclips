<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Listings\Feeds\FeedSync;
use App\Models\ListingFeed;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sync one listings feed (spec §14: every 30 minutes, per-agent filters, dedupe by ref). */
final class SyncFeed implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $feedId) {}

    public function handle(FeedSync $sync): void
    {
        $feed = TenantContext::global(fn (): ?ListingFeed => ListingFeed::unscopedByTenant()->with('tenant')->find($this->feedId));
        if ($feed === null || ! $feed->active) {
            return;
        }
        $sync->sync($feed);
    }
}
