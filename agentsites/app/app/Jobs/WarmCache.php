<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Tenancy\HostCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Spec §12.3 / §16: pre-render home, listings and contact so the first visitor hits a warm
 * page cache. The page cache itself is Phase 3; until then this primes the host cache only.
 */
final class WarmCache implements ShouldQueue
{
    use Queueable;

    /** @var list<string> */
    public const PAGES = ['/', '/listings', '/contact'];

    public function __construct(public readonly int $tenantId)
    {
        $this->afterCommit();
    }

    public function handle(HostCache $hosts): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }
        $hosts->lookup($tenant->primaryHost());
        Log::info('cache.warm', ['tenant_id' => $tenant->id, 'pages' => self::PAGES]);
    }
}
