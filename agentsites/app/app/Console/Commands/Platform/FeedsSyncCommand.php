<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Jobs\SyncDueFeeds;
use App\Listings\Feeds\FeedSync;
use App\Models\ListingFeed;
use App\Tenancy\TenantContext;

/** platform:feeds:sync — run a feed now (by id or site), or queue every due feed (spec §14). */
final class FeedsSyncCommand extends PlatformCommand
{
    protected $signature = 'platform:feeds:sync {--feed= : One feed id} {--tenant= : Every feed of one site (slug)} {--due : Queue every active feed that is due} {--no-media : Keep photo URLs}';

    protected $description = 'Sync listing feeds (GenericXml, PropertyFinder)';

    public function handle(FeedSync $sync): int
    {
        if ((bool) $this->option('due')) {
            $count = (new SyncDueFeeds)->handle();

            return $this->emit(['ok' => true, 'queued' => $count], "{$count} feed(s) queued");
        }

        $feeds = TenantContext::global(function () {
            $query = ListingFeed::unscopedByTenant()->with('tenant')->orderBy('id');
            if (($id = $this->option('feed')) !== null) {
                $query->whereKey((int) $id);
            } elseif (($slug = $this->option('tenant')) !== null) {
                $tenant = $this->findTenant((string) $slug);
                $query->where('tenant_id', $tenant !== null ? $tenant->id : 0)->where('active', true);
            } else {
                $query->whereRaw('1 = 0');
            }

            return $query->get();
        });
        if ($feeds->isEmpty()) {
            return $this->failWith('no matching feed (use --feed=<id>, --tenant=<slug> or --due)');
        }

        $results = [];
        foreach ($feeds as $feed) {
            $results[] = ['feed' => $feed->id, 'tenant' => $feed->tenant->slug, 'provider' => $feed->provider] + $sync->sync($feed, ! (bool) $this->option('no-media'));
        }
        if ((bool) $this->option('json')) {
            return $this->emit(['ok' => true, 'results' => $results]);
        }
        foreach ($results as $r) {
            $this->info(sprintf('feed #%d (%s, %s): %d fetched, %d created, %d updated, %d hidden, %d skipped, %d errors', $r['feed'], $r['tenant'], $r['provider'], $r['fetched'], $r['created'], $r['updated'], $r['hidden'], $r['skipped'], count($r['errors'])));
            foreach (array_slice($r['errors'], 0, 10) as $error) {
                $this->line('  '.$error);
            }
        }

        return self::SUCCESS;
    }
}
