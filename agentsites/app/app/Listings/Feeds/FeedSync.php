<?php

declare(strict_types=1);

namespace App\Listings\Feeds;

use App\Billing\PlanLimits;
use App\Listings\ListingCsv;
use App\Listings\ListingImporter;
use App\Models\Listing;
use App\Models\ListingFeed;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One feed sync (spec §14): fetch → per-agent filters → upsert by ref (source feed, feed_ref)
 * → listings the feed no longer carries become hidden → last_sync_at / last_error. Media is
 * cached locally by the importer. A failing feed keeps the last good listings.
 */
final class FeedSync
{
    /** @var list<string> */
    public const PROVIDERS = ['generic_xml', 'propertyfinder'];

    public function __construct(private readonly ListingImporter $importer) {}

    public static function adapter(string $provider): FeedAdapter
    {
        return match ($provider) {
            'generic_xml' => new GenericXmlAdapter,
            'propertyfinder' => new PropertyFinderAdapter,
            default => throw new FeedException("unknown feed provider '{$provider}'"),
        };
    }

    /**
     * @return array{fetched: int, created: int, updated: int, hidden: int, skipped: int, errors: list<string>}
     */
    public function sync(ListingFeed $feed, bool $fetchMedia = true): array
    {
        $tenant = $feed->tenant;
        $result = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'hidden' => 0, 'skipped' => 0, 'errors' => []];
        $seen = [];

        try {
            $listings = self::adapter($feed->provider)->fetch($feed);
            $remaining = PlanLimits::listingsRemaining($tenant);
            foreach ($listings as $item) {
                $result['fetched']++;
                $normalized = ListingCsv::normalize($item->row);
                $ref = (string) $normalized['attributes']['ref'];
                if ($normalized['errors'] !== []) {
                    $result['errors'][] = ($ref !== '' ? $ref : '#'.$result['fetched']).': '.implode('; ', $normalized['errors']);

                    continue;
                }
                if (! $this->passes($feed, $normalized['attributes'])) {
                    $result['skipped']++;

                    continue;
                }
                $exists = TenantContext::with($tenant, static fn (): bool => Listing::query()->where('ref', $ref)->exists());
                if (! $exists && $remaining !== null && $remaining <= 0) {
                    $result['errors'][] = $ref.': plan limit reached';

                    continue;
                }
                $created = $this->importer->upsert($tenant, $normalized['attributes'], $item->images, 'feed', $feed->id, $fetchMedia, $ref);
                $created ? $result['created']++ : $result['updated']++;
                if ($created && $remaining !== null) {
                    $remaining--;
                }
                $seen[] = $ref;
            }

            $result['hidden'] = TenantContext::with($tenant, static fn (): int => Listing::query()
                ->where('feed_id', $feed->id)
                ->where('status', '!=', 'hidden')
                ->whereNotIn('ref', $seen)
                ->get()
                ->each(static function (Listing $listing): void {
                    $listing->status = 'hidden';
                    $listing->save();
                })
                ->count());

            $feed->last_sync_at = now();
            $feed->last_error = $result['errors'] === [] ? null : implode("\n", array_slice($result['errors'], 0, 20));
            $feed->save();
        } catch (Throwable $e) {
            $feed->last_error = $e->getMessage();
            $feed->last_sync_at = now();
            $feed->save();
            $result['errors'][] = $e->getMessage();
            Log::warning('feed.sync.failed', ['feed_id' => $feed->id, 'tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Per-agent filters (spec §14): offering, min/max price, property types, communities.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function passes(ListingFeed $feed, array $attributes): bool
    {
        /** @var array<string, mixed> $filters */
        $filters = (array) ($feed->filters ?? []);
        if (($filters['offering'] ?? '') !== '' && $filters['offering'] !== $attributes['offering']) {
            return false;
        }
        if (isset($filters['min_price']) && (float) $filters['min_price'] > 0 && (float) $attributes['price'] < (float) $filters['min_price']) {
            return false;
        }
        if (isset($filters['max_price']) && (float) $filters['max_price'] > 0 && (float) $attributes['price'] > (float) $filters['max_price']) {
            return false;
        }
        $types = array_values(array_filter(array_map('strval', (array) ($filters['property_types'] ?? []))));
        if ($types !== [] && ! in_array($attributes['property_type'], $types, true)) {
            return false;
        }
        $communities = array_map(static fn (string $c): string => strtolower(trim($c)), array_map('strval', (array) ($filters['communities'] ?? [])));
        $communities = array_values(array_filter($communities));
        if ($communities !== [] && ! in_array(strtolower((string) ($attributes['community'] ?? '')), $communities, true)) {
            return false;
        }

        return true;
    }
}
