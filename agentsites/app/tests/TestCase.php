<?php

namespace Tests;

use App\Models\Account;
use App\Models\Tenant;
use App\Platform\Hosts;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Theme CSS is built by Vite at deploy; tests render without the manifest.
        $this->withoutVite();
    }

    /** A tenant with its subdomain host row, the way provisioning creates it (live = published, so indexable). */
    protected function makeTenant(string $slug, string $status = Tenant::STATUS_LIVE, array $config = []): Tenant
    {
        if ($status === Tenant::STATUS_LIVE && ! isset($config['seo']['noindex'])) {
            $config['seo']['noindex'] = false;
        }
        $tenant = Tenant::factory()
            ->for(Account::factory())
            ->withConfig($config)
            ->state(['slug' => $slug, 'status' => $status, 'published_at' => $status === Tenant::STATUS_DRAFT ? null : now()])
            ->create();

        TenantContext::with($tenant, fn () => $tenant->domains()->create([
            'host' => Hosts::tenant($slug),
            'type' => 'subdomain',
            'role' => 'primary',
            'verified' => true,
            'dns_status' => 'verified',
            'ssl_status' => 'issued',
        ]));

        return $tenant;
    }
}
