<?php

declare(strict_types=1);

namespace App\Listings;

use App\Media\ImageProcessor;
use App\Media\MediaStore;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * A listing photo becomes three WebP variants on the media disk (spec §16) and one entry in
 * listings.media: {path, variants{thumb,card,hero}, width, height, source, remote?}.
 */
final class ListingPhotos
{
    public function __construct(private readonly ImageProcessor $images, private readonly MediaStore $store) {}

    /**
     * @return array{path: string, variants: array{thumb: string, card: string, hero: string}, width: int, height: int, source: string, remote?: string}
     */
    public function store(Tenant $tenant, string $ref, string $binary, string $source = 'upload', ?string $remote = null): array
    {
        $variants = $this->images->variants($binary);
        $base = 'listings/'.Str::slug($ref, '-').'/photo';
        $paths = [];
        foreach (['thumb', 'card', 'hero'] as $size) {
            $paths[$size] = MediaStore::publicPath($this->store->put($tenant, $variants[$size], $base.'-'.$size));
        }

        $entry = [
            'path' => $paths['hero'],
            'variants' => $paths,
            'width' => $variants['width'],
            'height' => $variants['height'],
            'source' => $source,
        ];
        if ($remote !== null) {
            $entry['remote'] = $remote;
        }

        return $entry;
    }
}
