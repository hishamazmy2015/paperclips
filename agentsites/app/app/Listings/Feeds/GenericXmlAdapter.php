<?php

declare(strict_types=1);

namespace App\Listings\Feeds;

use App\Listings\ListingCsv;
use App\Models\ListingFeed;

/**
 * The platform's own feed format (docs/LISTINGS.md): <listings><listing> with one child element
 * per CSV column (ref, title_en, offering, price, …) and images as <images><image>url</image>
 * or a `;`-separated <media>. Anything an agency's CRM can export.
 */
final class GenericXmlAdapter implements FeedAdapter
{
    use FetchesXml;

    public function key(): string
    {
        return 'generic_xml';
    }

    public function fetch(ListingFeed $feed): iterable
    {
        $xml = $this->loadXml($feed);
        foreach ($xml->listing as $node) {
            $row = [];
            foreach (ListingCsv::COLUMNS as $column) {
                if ($column !== 'media' && isset($node->{$column})) {
                    $row[$column] = trim((string) $node->{$column});
                }
            }
            $images = [];
            if (isset($node->images)) {
                foreach ($node->images->image as $image) {
                    $images[] = trim((string) $image);
                }
            }
            if (isset($node->media)) {
                foreach (explode(';', (string) $node->media) as $url) {
                    $images[] = trim($url);
                }
            }
            $row['media'] = implode(';', array_values(array_filter($images)));

            yield new FeedListing($row, array_values(array_filter($images)));
        }
    }
}
