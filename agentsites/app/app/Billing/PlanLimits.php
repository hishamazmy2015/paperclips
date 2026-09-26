<?php

declare(strict_types=1);

namespace App\Billing;

use App\Models\Account;
use App\Models\Listing;
use App\Models\Tenant;
use App\Tenancy\TenantContext;

/**
 * Plan limits from config/plans.php (spec §14: Gate::limit() everywhere). The trial carries the
 * pro limits; an unknown plan key falls back to the default plan. Billing state machines and
 * upgrades arrive in Phase 5; this is the read side every feature checks today.
 */
final class PlanLimits
{
    /** @return array<string, int> */
    public static function limits(Account $account): array
    {
        /** @var array<string, array<string, mixed>> $plans */
        $plans = config('plans.plans', []);
        $plan = $plans[$account->plan_key] ?? $plans[(string) config('plans.default')] ?? [];

        /** @var array<string, int> $limits */
        $limits = (array) ($plan['limits'] ?? []);

        return $limits;
    }

    public static function limit(Account $account, string $name): ?int
    {
        $limits = self::limits($account);

        return isset($limits[$name]) ? (int) $limits[$name] : null;
    }

    public static function feature(Account $account, string $name): bool
    {
        /** @var array<string, array<string, mixed>> $plans */
        $plans = config('plans.plans', []);
        $plan = $plans[$account->plan_key] ?? $plans[(string) config('plans.default')] ?? [];

        return (bool) (((array) ($plan['features'] ?? []))[$name] ?? false);
    }

    /** Real listings (demo ones never count). */
    public static function listingsUsed(Tenant $tenant): int
    {
        return TenantContext::with($tenant, static fn (): int => Listing::query()->real()->count());
    }

    /** How many more listings the plan allows; null = unlimited. */
    public static function listingsRemaining(Tenant $tenant): ?int
    {
        $limit = self::limit($tenant->account, 'listings');

        return $limit === null ? null : max(0, $limit - self::listingsUsed($tenant));
    }

    public static function canAddListings(Tenant $tenant, int $count = 1): bool
    {
        $remaining = self::listingsRemaining($tenant);

        return $remaining === null || $remaining >= $count;
    }
}
