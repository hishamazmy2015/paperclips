<?php

declare(strict_types=1);

namespace App\Listings\Feeds;

use App\Models\ListingFeed;

/**
 * A listings feed provider (spec §14): GenericXml first, then PropertyFinder. Adapters only
 * fetch and translate; FeedSync filters, dedupes by ref, caches media and hides what is gone.
 */
interface FeedAdapter
{
    public function key(): string;

    /**
     * @return iterable<FeedListing>
     *
     * @throws FeedException
     */
    public function fetch(ListingFeed $feed): iterable;
}
