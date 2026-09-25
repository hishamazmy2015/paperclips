<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Tenant;
use App\Platform\EventLog;
use App\Platform\Funnel;
use Illuminate\Support\Facades\Artisan;

// Spec §13: views, completions, drop-off %, median seconds per step, split by device and locale.

it('reports the onboarding funnel from the events table', function (): void {
    $events = app(EventLog::class);
    $accounts = Account::factory()->count(3)->create();
    $tenants = $accounts->map(fn (Account $account): Tenant => Tenant::factory()->for($account)->create());

    $events->record('onboarding.started');
    $events->record('onboarding.started');
    $events->record('onboarding.started');
    foreach ($accounts as $i => $account) {
        $tenant = $tenants[$i];
        $events->record('account.created', [], account: $account);
        $events->record('step.viewed', ['n' => 1], tenant: $tenant, account: $account);
        $this->travel(10 + $i * 10)->seconds();
        $events->record('step.completed', ['n' => 1], tenant: $tenant, account: $account);
        $events->record('step.viewed', ['n' => 2], tenant: $tenant, account: $account);
        if ($i < 2) {
            $this->travel(20)->seconds();
            $events->record('step.completed', ['n' => 2], tenant: $tenant, account: $account);
            $events->record('step.viewed', ['n' => 3], tenant: $tenant, account: $account);
        }
        if ($i === 0) {
            $events->record('step.completed', ['n' => 3], tenant: $tenant, account: $account);
            $events->record('published', ['first' => true], tenant: $tenant, account: $account);
        }
    }

    $report = app(Funnel::class)->report(now()->subDay(), now()->addMinute());
    expect($report['started'])->toBe(3)
        ->and($report['signed_up'])->toBe(3)
        ->and($report['published'])->toBe(1)
        ->and($report['completion_rate'])->toBe(0.333)
        ->and($report['steps'][0])->toMatchArray(['n' => 1, 'viewed' => 3, 'completed' => 3, 'drop_off' => 0.0, 'median_seconds' => 20.0])
        ->and($report['steps'][1])->toMatchArray(['n' => 2, 'viewed' => 3, 'completed' => 2, 'drop_off' => 0.333, 'median_seconds' => 20.0])
        ->and($report['steps'][2])->toMatchArray(['n' => 3, 'viewed' => 2, 'completed' => 1, 'drop_off' => 0.5])
        ->and($report['by_locale'])->toHaveKey('en')
        ->and($report['by_locale']['en'])->toBe(['started' => 3, 'published' => 1]);

    Artisan::call('platform:funnel', ['--days' => 1, '--json' => true]);
    $json = json_decode(Artisan::output(), true);
    expect($json['published'])->toBe(1)->and($json['steps'])->toHaveCount(3);

    Artisan::call('platform:funnel', ['--days' => 1]);
    expect(Artisan::output())->toContain('3 started, 3 signed up, 1 published')->toContain('Drop-off');
    expect(Funnel::median([]))->toBeNull()->and(Funnel::median([3, 1, 2]))->toBe(2.0)->and(Funnel::median([1, 2, 3, 4]))->toBe(2.5);
});
