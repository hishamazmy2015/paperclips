<?php

declare(strict_types=1);

namespace App\Listings;

use App\Media\ImageProcessor;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Downloads a listing photo from a CSV/feed URL with size and type limits (spec §14, §17). */
final class RemoteImageFetcher
{
    public const TIMEOUT_SECONDS = 15;

    /** @throws RuntimeException */
    public function fetch(string $url): string
    {
        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new RuntimeException('not an http(s) URL');
        }
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->withOptions(['stream' => false])->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException('download failed: '.$e->getMessage(), 0, $e);
        }
        if (! $response->successful()) {
            throw new RuntimeException('download failed: HTTP '.$response->status());
        }
        $binary = $response->body();
        if (strlen($binary) > ImageProcessor::MAX_BYTES) {
            throw new RuntimeException('image larger than 8 MB');
        }
        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            throw new RuntimeException('not an image');
        }

        return $binary;
    }
}
