<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Models\Domain;
use App\Models\RedirectRule;
use App\Models\Tenant;
use App\Platform\Hosts;
use App\Provisioning\Exceptions\SlugUnavailable;
use App\Tenancy\ReservedSlugs;
use Illuminate\Support\Str;

/**
 * Slug derivation, availability and reservation (spec §12.1–2, §13 S3). A slug is available
 * when it has the right shape, is not reserved, is not a tenant (even a deleted one), and its
 * host is neither a domain row nor an active redirect source.
 */
final class Slugs
{
    public function __construct(private readonly ReservedSlugs $reserved) {}

    /** Derive a slug from a display name; Arabic (and everything else) is transliterated. */
    public static function fromName(string $name): string
    {
        $slug = Str::slug(Str::ascii(trim($name)));
        $slug = trim(substr($slug, 0, 40), '-');

        return strlen($slug) >= 3 ? $slug : 'agent-'.strtolower(Str::random(6));
    }

    public function isValidShape(string $slug): bool
    {
        return $this->reserved->isValidShape($slug);
    }

    public function isAvailable(string $slug): bool
    {
        return $this->reason($slug) === null;
    }

    /** Why a slug cannot be used, or null when it can. */
    public function reason(string $slug): ?string
    {
        if (! $this->reserved->isValidShape($slug)) {
            return 'invalid';
        }
        if ($this->reserved->isReserved($slug)) {
            return 'reserved';
        }
        if (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            return 'taken';
        }
        $host = Hosts::tenant($slug);
        if (Domain::unscopedByTenant()->withTrashed()->where('host', $host)->exists()) {
            return 'taken';
        }
        if (RedirectRule::query()->active()->where('from_host', $host)->exists()) {
            return 'taken';
        }

        return null;
    }

    /**
     * Up to $count available alternatives for a wanted slug.
     *
     * @return list<string>
     */
    public function suggest(string $base, int $count = 3): array
    {
        $base = self::fromName($base);
        $candidates = [$base.'-dubai', $base.'-homes', $base.'-properties', $base.'-uae', $base.'-realestate'];
        for ($i = 2; $i <= 30; $i++) {
            $candidates[] = $base.'-'.$i;
        }

        $out = [];
        foreach ($candidates as $candidate) {
            if ($this->isAvailable($candidate)) {
                $out[] = $candidate;
                if (count($out) === $count) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * The slug to create a tenant with: an explicit wanted slug must be available (else
     * SlugUnavailable with suggestions); a derived one gets a numeric suffix when taken.
     *
     * @throws SlugUnavailable
     */
    public function reserve(?string $wanted, string $name): string
    {
        if ($wanted !== null && $wanted !== '') {
            $wanted = strtolower(trim($wanted));
            $reason = $this->reason($wanted);
            if ($reason !== null) {
                throw new SlugUnavailable($wanted, $reason, $this->suggest($wanted));
            }

            return $wanted;
        }

        $base = self::fromName($name);
        if ($this->isAvailable($base)) {
            return $base;
        }
        foreach ($this->suggest($base, 1) as $suggestion) {
            return $suggestion;
        }

        return $base.'-'.strtolower(Str::random(4));
    }
}
