<?php

declare(strict_types=1);

namespace App\Listings;

use App\Billing\PlanLimits;
use App\Models\Listing;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Listings from a CSV or a feed into one tenant (spec §14): validated row by row, deduped by
 * ref (update, never duplicate), plan limit respected, photos cached locally when reachable,
 * and every rejected row reported with its number and reason.
 */
final class ListingImporter
{
    public function __construct(
        private readonly ListingPhotos $photos,
        private readonly RemoteImageFetcher $fetcher,
    ) {}

    /**
     * @return array{total: int, created: int, updated: int, errors: list<array{row: int, ref: string, error: string}>, errors_path: string|null}
     */
    public function importCsv(Tenant $tenant, string $path, bool $dryRun = false, bool $fetchMedia = true): array
    {
        try {
            $rows = ListingCsv::rows($path);
        } catch (InvalidArgumentException $e) {
            return ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => [['row' => 1, 'ref' => '', 'error' => $e->getMessage()]], 'errors_path' => null];
        }

        $result = ['total' => count($rows), 'created' => 0, 'updated' => 0, 'errors' => [], 'errors_path' => null];
        $remaining = PlanLimits::listingsRemaining($tenant);
        $seen = [];

        foreach ($rows as $number => $row) {
            $normalized = ListingCsv::normalize($row);
            $ref = (string) $normalized['attributes']['ref'];
            if ($normalized['errors'] !== []) {
                $result['errors'][] = ['row' => $number, 'ref' => $ref, 'error' => implode('; ', $normalized['errors'])];

                continue;
            }
            if (isset($seen[strtolower($ref)])) {
                $result['errors'][] = ['row' => $number, 'ref' => $ref, 'error' => 'duplicate ref in this file'];

                continue;
            }
            $seen[strtolower($ref)] = true;

            $exists = TenantContext::with($tenant, static fn (): bool => Listing::query()->where('ref', $ref)->exists());
            if (! $exists && $remaining !== null && $remaining <= 0) {
                $result['errors'][] = ['row' => $number, 'ref' => $ref, 'error' => 'plan limit reached ('.PlanLimits::limit($tenant->account, 'listings').' listings)'];

                continue;
            }
            if ($dryRun) {
                $exists ? $result['updated']++ : $result['created']++;
                if (! $exists && $remaining !== null) {
                    $remaining--;
                }

                continue;
            }

            try {
                $created = $this->upsert($tenant, $normalized['attributes'], $normalized['media'], 'csv', null, $fetchMedia);
            } catch (Throwable $e) {
                $result['errors'][] = ['row' => $number, 'ref' => $ref, 'error' => $e->getMessage()];

                continue;
            }
            $created ? $result['created']++ : $result['updated']++;
            if ($created && $remaining !== null) {
                $remaining--;
            }
        }

        if ($result['errors'] !== [] && ! $dryRun) {
            $result['errors_path'] = self::writeErrors($path, $result['errors']);
        }

        return $result;
    }

    /**
     * Create or update one listing by ref. Photos: existing cached entries for the same remote
     * URL are kept; new URLs are fetched and cached when $fetchMedia, else stored as URLs.
     *
     * @param  array<string, mixed>  $attributes  normalised by ListingCsv::normalize
     * @param  list<string>  $mediaUrls
     * @return bool true when created
     */
    public function upsert(Tenant $tenant, array $attributes, array $mediaUrls, string $source, ?int $feedId = null, bool $fetchMedia = true, ?string $feedRef = null): bool
    {
        return TenantContext::with($tenant, function () use ($tenant, $attributes, $mediaUrls, $source, $feedId, $fetchMedia, $feedRef): bool {
            $listing = Listing::query()->where('ref', (string) $attributes['ref'])->first();
            $created = $listing === null;
            $listing ??= new Listing(['tenant_id' => $tenant->id, 'ref' => (string) $attributes['ref']]);

            /** @var array<int, mixed> $existing */
            $existing = $listing->getAttribute('media') ?? [];
            $byRemote = [];
            foreach ($existing as $entry) {
                if (is_array($entry) && isset($entry['remote'])) {
                    $byRemote[(string) $entry['remote']] = $entry;
                }
            }

            $media = [];
            foreach ($mediaUrls as $url) {
                if (isset($byRemote[$url])) {
                    $media[] = $byRemote[$url];

                    continue;
                }
                if (! $fetchMedia) {
                    $media[] = $url;

                    continue;
                }
                try {
                    $media[] = $this->photos->store($tenant, (string) $attributes['ref'], $this->fetcher->fetch($url), 'remote', $url);
                } catch (Throwable $e) {
                    Log::warning('listing.media.uncached', ['tenant_id' => $tenant->id, 'ref' => $attributes['ref'], 'error' => $e->getMessage()]);
                    $media[] = $url;
                }
            }

            $listing->fill($attributes + ['source' => $source, 'feed_id' => $feedId, 'feed_ref' => $feedRef, 'media' => $media]);
            $listing->save();

            return $created;
        });
    }

    public static function errorsPath(string $source): string
    {
        return preg_replace('/\.csv$/i', '', $source).'.errors.csv';
    }

    /** @param  list<array{row: int, ref: string, error: string}>  $errors */
    private static function writeErrors(string $source, array $errors): string
    {
        $path = self::errorsPath($source);
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new InvalidArgumentException("cannot write {$path}");
        }
        fputcsv($handle, ['row', 'ref', 'error'], escape: '');
        foreach ($errors as $error) {
            fputcsv($handle, [$error['row'], $error['ref'], $error['error']], escape: '');
        }
        fclose($handle);

        return $path;
    }
}
