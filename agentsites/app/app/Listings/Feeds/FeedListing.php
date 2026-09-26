<?php

declare(strict_types=1);

namespace App\Listings\Feeds;

/** One listing as a feed delivers it, in the CSV column vocabulary (ListingCsv), plus its image URLs. */
final class FeedListing
{
    /**
     * @param  array<string, string>  $row  ListingCsv columns (ref, title_en, offering, …) as strings
     * @param  list<string>  $images
     */
    public function __construct(public readonly array $row, public readonly array $images = []) {}

    public function ref(): string
    {
        return trim((string) ($this->row['ref'] ?? ''));
    }
}
