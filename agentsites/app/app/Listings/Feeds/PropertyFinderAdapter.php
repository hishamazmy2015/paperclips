<?php

declare(strict_types=1);

namespace App\Listings\Feeds;

use App\Models\ListingFeed;

/**
 * Property Finder XML export (spec §14, second adapter): <list><property> elements with
 * reference_number, title_en/ar, description_en/ar, offering_type (RS/RR/CS/CR), property_type
 * (AP, VH, TH, PH, …), price (a value, or <yearly> for rentals), bedroom, bathroom, size,
 * community, sub_community, city and <photo><url>. Fields it does not carry stay empty.
 */
final class PropertyFinderAdapter implements FeedAdapter
{
    use FetchesXml;

    /** @var array<string, string> */
    private const TYPES = ['AP' => 'apartment', 'VH' => 'villa', 'TH' => 'townhouse', 'PH' => 'penthouse', 'DX' => 'duplex', 'CD' => 'compound', 'LP' => 'land', 'OF' => 'office', 'RE' => 'retail', 'WH' => 'warehouse', 'ST' => 'studio'];

    public function key(): string
    {
        return 'propertyfinder';
    }

    public function fetch(ListingFeed $feed): iterable
    {
        $xml = $this->loadXml($feed);
        foreach ($xml->property as $node) {
            $offering = strtoupper(self::text($node, 'offering_type'));
            $type = strtoupper(self::text($node, 'property_type'));
            $price = self::text($node, 'price');
            if ($price === '' && isset($node->price->yearly)) {
                $price = trim((string) $node->price->yearly);
            }
            $bedrooms = self::text($node, 'bedroom');
            $images = [];
            if (isset($node->photo)) {
                foreach ($node->photo->url as $url) {
                    $images[] = trim((string) $url);
                }
            }
            $community = self::text($node, 'community');
            $sub = self::text($node, 'sub_community');

            $row = [
                'ref' => self::text($node, 'reference_number'),
                'title_en' => self::text($node, 'title_en'),
                'title_ar' => self::text($node, 'title_ar'),
                'description_en' => self::text($node, 'description_en'),
                'description_ar' => self::text($node, 'description_ar'),
                'offering' => str_ends_with($offering, 'R') ? 'rent' : 'sale',
                'property_type' => self::TYPES[$type] ?? 'other',
                'price' => $price,
                'currency' => 'AED',
                'bedrooms' => strtolower($bedrooms) === 'studio' ? '0' : $bedrooms,
                'bathrooms' => self::text($node, 'bathroom'),
                'area_sqft' => self::text($node, 'size'),
                'community' => $community !== '' ? $community : $sub,
                'city' => self::text($node, 'city'),
                'status' => 'available',
                'featured' => self::text($node, 'featured'),
                'media' => implode(';', array_values(array_filter($images))),
            ];
            if (($row['bedrooms'] === '0' || $row['bedrooms'] === '') && $type === 'ST') {
                $row['property_type'] = 'studio';
                $row['bedrooms'] = '0';
            }

            yield new FeedListing($row, array_values(array_filter($images)));
        }
    }
}
