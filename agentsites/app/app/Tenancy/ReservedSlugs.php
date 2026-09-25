<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\ReservedSlug;

/** Slug policy: shape, platform reservations (config + table). Spec §7, §12. */
final class ReservedSlugs
{
    public function isValidShape(string $slug): bool
    {
        $min = (int) config('reserved_slugs.min_length', 3);
        $max = (int) config('reserved_slugs.max_length', 40);
        $len = strlen($slug);

        return $len >= $min && $len <= $max && (bool) preg_match((string) config('reserved_slugs.pattern'), $slug);
    }

    public function isReserved(string $slug): bool
    {
        /** @var list<string> $reserved */
        $reserved = config('reserved_slugs.reserved', []);
        if (in_array($slug, $reserved, true)) {
            return true;
        }

        /** @var list<string> $prefixes */
        $prefixes = config('reserved_slugs.prefixes', []);
        foreach ($prefixes as $prefix) {
            if (str_starts_with($slug, $prefix)) {
                return true;
            }
        }

        return ReservedSlug::query()->where('slug', $slug)->exists();
    }

    public function reserve(string $slug, ?string $reason = null): void
    {
        ReservedSlug::query()->firstOrCreate(['slug' => $slug], ['reason' => $reason]);
    }
}
