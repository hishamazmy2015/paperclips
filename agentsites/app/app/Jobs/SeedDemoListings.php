<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Listings\DemoSeeder;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Spec §12.3: dispatched after commit when a new tenant has no listings. */
final class SeedDemoListings implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $tenantId)
    {
        $this->afterCommit();
    }

    public function handle(DemoSeeder $seeder): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant !== null) {
            $seeder->seed($tenant);
        }
    }
}
