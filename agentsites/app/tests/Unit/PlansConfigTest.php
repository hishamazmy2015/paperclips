<?php

declare(strict_types=1);

// Spec §14: plans are config; the trial carries every pro feature.

it('defines exactly the four plans from the spec', function (): void {
    expect(array_keys(config('plans.plans')))->toBe(['trial', 'starter', 'pro', 'brokerage'])
        ->and(config('plans.default'))->toBe('trial')
        ->and(config('plans.trial_days'))->toBe(14);
});

it('gives every plan the same limits and feature keys', function (): void {
    foreach (config('plans.plans') as $key => $plan) {
        expect(array_keys($plan['limits']))->toBe(['listings', 'custom_domains', 'members', 'storage_mb'], "limits of {$key}")
            ->and(array_keys($plan['features']))->toBe(['remove_branding', 'custom_domain', 'feeds', 'analytics_pro'], "features of {$key}")
            ->and($plan['name'])->toHaveKeys(['en', 'ar'])
            ->and($plan['price_monthly'])->toBeGreaterThanOrEqual(0)
            ->and($plan['price_yearly'])->toBeGreaterThanOrEqual(0);
    }
});

it('gives the trial everything pro has', function (): void {
    expect(config('plans.plans.trial.features'))->toBe(config('plans.plans.pro.features'))
        ->and(config('plans.plans.trial.limits'))->toBe(config('plans.plans.pro.limits'));
});
