<?php

declare(strict_types=1);

namespace App\Listings\Feeds;

use App\Models\ListingFeed;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

/** Shared transport for XML feeds: URL + optional header from the encrypted credentials, 20 s, no external entities. */
trait FetchesXml
{
    /** @throws FeedException */
    protected function loadXml(ListingFeed $feed): SimpleXMLElement
    {
        /** @var array<string, mixed> $credentials */
        $credentials = is_array($feed->credentials) ? $feed->credentials : (array) json_decode((string) $feed->credentials, true);
        $url = (string) ($credentials['url'] ?? '');
        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new FeedException('feed URL missing or not http(s)');
        }

        try {
            $request = Http::timeout(20)->accept('application/xml');
            $header = (string) ($credentials['auth_header'] ?? '');
            if ($header !== '') {
                $request = $request->withHeaders([$header => (string) ($credentials['auth_value'] ?? '')]);
            }
            $response = $request->get($url);
        } catch (Throwable $e) {
            throw new FeedException('fetch failed: '.$e->getMessage(), 0, $e);
        }
        if (! $response->successful()) {
            throw new FeedException('fetch failed: HTTP '.$response->status());
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            throw new FeedException('invalid XML');
        }

        return $xml;
    }

    protected static function text(SimpleXMLElement $node, string ...$names): string
    {
        foreach ($names as $name) {
            if (isset($node->{$name})) {
                return trim((string) $node->{$name});
            }
        }

        return '';
    }
}
