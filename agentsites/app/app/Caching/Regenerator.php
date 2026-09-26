<?php

declare(strict_types=1);

namespace App\Caching;

use App\Jobs\RegenerateBatch;
use App\Models\RegenerateRun;
use App\Models\Tenant;
use Throwable;

/**
 * regenerate = purge + warm (spec §16). One site runs inline; --all runs in batches of
 * queued jobs with a per-tenant checkpoint in regenerate_runs, so a killed worker resumes
 * exactly where it stopped (A5) and never re-warms what is done.
 */
final class Regenerator
{
    public function __construct(private readonly PageCache $pages, private readonly SiteWarmer $warmer) {}

    /** @return array{purged: bool, warmed: int} */
    public function one(Tenant $tenant, bool $warm = true): array
    {
        $this->pages->purgeTenant($tenant);

        return ['purged' => true, 'warmed' => $warm ? $this->warmer->warm($tenant) : 0];
    }

    public function startAll(int $batch = 50, bool $warm = true): RegenerateRun
    {
        $this->pages->purgeAll();
        $run = RegenerateRun::query()->create([
            'status' => RegenerateRun::STATUS_PENDING,
            'total_tenants' => Tenant::query()->live()->count(),
            'batch_size' => max(1, $batch),
            'warm' => $warm,
            'started_at' => now(),
        ]);
        if ($run->total_tenants === 0) {
            $run->update(['status' => RegenerateRun::STATUS_COMPLETED, 'finished_at' => now()]);

            return $run;
        }
        RegenerateBatch::dispatch($run->id);

        return $run->refresh();
    }

    /** Continue a killed or failed run from its checkpoint; a completed run stays completed. */
    public function resume(RegenerateRun $run): RegenerateRun
    {
        if ($run->status === RegenerateRun::STATUS_COMPLETED) {
            return $run;
        }
        $run->update(['status' => RegenerateRun::STATUS_PENDING, 'last_error' => null]);
        RegenerateBatch::dispatch($run->id);

        return $run->refresh();
    }

    /** Process one batch after the checkpoint; queue the next one when tenants remain. */
    public function processBatch(RegenerateRun $run): void
    {
        $run->update(['status' => RegenerateRun::STATUS_RUNNING]);
        $tenants = Tenant::query()->live()->where('id', '>', $run->last_tenant_id)->orderBy('id')->limit($run->batch_size)->get();

        foreach ($tenants as $tenant) {
            try {
                $result = $this->one($tenant, $run->warm);
            } catch (Throwable $e) {
                $run->update(['status' => RegenerateRun::STATUS_FAILED, 'last_error' => 'tenant '.$tenant->id.': '.$e->getMessage()]);

                return;
            }
            $run->processed_tenants++;
            $run->warmed_pages += $result['warmed'];
            $run->last_tenant_id = $tenant->id;
            $run->save();
        }

        if ($tenants->count() < $run->batch_size || ! Tenant::query()->live()->where('id', '>', $run->last_tenant_id)->exists()) {
            $run->update(['status' => RegenerateRun::STATUS_COMPLETED, 'finished_at' => now()]);

            return;
        }
        RegenerateBatch::dispatch($run->id);
    }
}
