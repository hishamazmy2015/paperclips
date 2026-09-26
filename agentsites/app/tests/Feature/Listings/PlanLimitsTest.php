<?php

declare(strict_types=1);

use App\Billing\PlanLimits;
use App\Models\Listing;

// Spec §14: limits and features come from config/plans.php; demo listings never count.

it('reads limits and features per plan, counting real listings only', function (): void {
    $tenant = $this->makeTenant('ahmed');
    Listing::factory()->count(3)->create(['tenant_id' => $tenant->id]);
    Listing::factory()->demo()->count(6)->create(['tenant_id' => $tenant->id]);

    expect(PlanLimits::limit($tenant->account, 'listings'))->toBe(100) // trial carries pro limits
        ->and(PlanLimits::listingsUsed($tenant))->toBe(3)
        ->and(PlanLimits::listingsRemaining($tenant))->toBe(97)
        ->and(PlanLimits::canAddListings($tenant, 97))->toBeTrue()
        ->and(PlanLimits::canAddListings($tenant, 98))->toBeFalse()
        ->and(PlanLimits::feature($tenant->account, 'custom_domain'))->toBeTrue();

    $tenant->account->update(['plan_key' => 'starter']);
    $tenant = $tenant->fresh();
    expect(PlanLimits::limit($tenant->account, 'listings'))->toBe(25)
        ->and(PlanLimits::feature($tenant->account, 'custom_domain'))->toBeFalse()
        ->and(PlanLimits::limit($tenant->account, 'unknown'))->toBeNull();

    $tenant->account->update(['plan_key' => 'no-such-plan']);
    expect(PlanLimits::limits($tenant->fresh()->account))->toBe(config('plans.plans.trial.limits'));
});
