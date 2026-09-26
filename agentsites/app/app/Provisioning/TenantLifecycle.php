<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Caching\PageCache;
use App\Models\Domain;
use App\Models\Tenant;
use App\Platform\Audit;
use App\Platform\EventLog;
use App\Provisioning\Exceptions\SlugUnavailable;
use App\Tenancy\HostCache;
use App\Tenancy\RedirectRules;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Suspend / restore / delete / rename — all data changes (goal G1), all audited, all
 * invalidating the host cache so the edge sees the new state on the next request.
 */
final class TenantLifecycle
{
    public const RENAME_REDIRECT_DAYS = 90;

    public function __construct(
        private readonly HostCache $hosts,
        private readonly PageCache $pages,
        private readonly RedirectRules $redirects,
        private readonly Slugs $slugs,
        private readonly Audit $audit,
        private readonly EventLog $events,
    ) {}

    public function suspend(Tenant $tenant, string $reason = ''): Tenant
    {
        $tenant->status = Tenant::STATUS_SUSPENDED;
        $tenant->save();
        $this->hosts->forgetTenant($tenant);
        $this->pages->purgeTenant($tenant);
        $this->audit->record('tenant.suspended', ['reason' => $reason], tenant: $tenant, account: $tenant->account, targetType: Tenant::class, targetId: $tenant->id);
        $this->events->record('tenant.suspended', ['reason' => $reason], tenant: $tenant, account: $tenant->account);

        return $tenant;
    }

    public function restore(Tenant $tenant): Tenant
    {
        if ($tenant->trashed()) {
            $tenant->restore();
            TenantContext::global(fn () => Domain::unscopedByTenant()->onlyTrashed()->where('tenant_id', $tenant->id)->restore());
        }
        $tenant->status = $tenant->published_at === null ? Tenant::STATUS_DRAFT : Tenant::STATUS_LIVE;
        $tenant->save();
        $this->hosts->forgetTenant($tenant);
        $this->pages->purgeTenant($tenant);
        $this->audit->record('tenant.restored', [], tenant: $tenant, account: $tenant->account, targetType: Tenant::class, targetId: $tenant->id);
        $this->events->record('tenant.restored', [], tenant: $tenant, account: $tenant->account);

        return $tenant;
    }

    /** Soft delete (status deleted); the purge job removes the rows after 30 days (spec §8). */
    public function delete(Tenant $tenant, string $reason = ''): Tenant
    {
        DB::transaction(function () use ($tenant, $reason): void {
            $tenant->status = Tenant::STATUS_DELETED;
            $tenant->save();
            TenantContext::global(fn () => Domain::unscopedByTenant()->where('tenant_id', $tenant->id)->get()->each->delete());
            $tenant->delete();
            $this->audit->record('tenant.deleted', ['reason' => $reason], tenant: $tenant, account: $tenant->account, targetType: Tenant::class, targetId: $tenant->id);
            $this->events->record('tenant.deleted', ['reason' => $reason], tenant: $tenant, account: $tenant->account);
        });
        $this->hosts->forgetTenant($tenant);
        $this->pages->purgeTenant($tenant);

        return $tenant;
    }

    /**
     * A draft's slug is free to change while onboarding (spec §8: immutable only after
     * publish, §13 S3): the subdomain host row follows it and no redirect is written.
     *
     * @throws SlugUnavailable
     */
    public function changeDraftSlug(Tenant $tenant, string $newSlug): Tenant
    {
        $newSlug = strtolower(trim($newSlug));
        if ($newSlug === $tenant->slug) {
            return $tenant;
        }
        if ($tenant->published_at !== null) {
            throw new SlugUnavailable($newSlug, 'published', []);
        }
        $reason = $this->slugs->reason($newSlug);
        if ($reason !== null) {
            throw new SlugUnavailable($newSlug, $reason, $this->slugs->suggest($newSlug));
        }

        $oldHost = $tenant->subdomainHost();
        DB::transaction(function () use ($tenant, $newSlug, $oldHost): void {
            $tenant->slug = $newSlug;
            $tenant->save();
            $newHost = $tenant->subdomainHost();

            TenantContext::global(function () use ($tenant, $oldHost, $newHost): void {
                $domain = Domain::unscopedByTenant()->where('tenant_id', $tenant->id)->where('host', $oldHost)->first();
                if ($domain !== null) {
                    $domain->host = $newHost;
                    $domain->save();
                }
            });
            $this->audit->record('tenant.slug_changed', ['to' => $newSlug], tenant: $tenant, account: $tenant->account, targetType: Tenant::class, targetId: $tenant->id);
        });

        $this->hosts->forgetHost($oldHost);
        $this->hosts->forgetTenant($tenant);
        $this->pages->purgeTenant($tenant);

        return $tenant;
    }

    /**
     * The one legitimate slug change (spec §8, §15): the old subdomain host becomes a 301
     * redirect rule for RENAME_REDIRECT_DAYS.
     *
     * @throws SlugUnavailable
     */
    public function rename(Tenant $tenant, string $newSlug): Tenant
    {
        $newSlug = strtolower(trim($newSlug));
        $reason = $this->slugs->reason($newSlug);
        if ($reason !== null) {
            throw new SlugUnavailable($newSlug, $reason, $this->slugs->suggest($newSlug));
        }

        $oldSlug = $tenant->slug;
        $oldHost = $tenant->subdomainHost();

        DB::transaction(function () use ($tenant, $newSlug, $oldHost, $oldSlug): void {
            Tenant::whileRenaming(function () use ($tenant, $newSlug): void {
                $tenant->slug = $newSlug;
                $tenant->save();
            });
            $newHost = $tenant->subdomainHost();

            TenantContext::global(function () use ($tenant, $oldHost, $newHost): void {
                $domain = Domain::unscopedByTenant()->where('tenant_id', $tenant->id)->where('host', $oldHost)->first();
                if ($domain !== null) {
                    $domain->host = $newHost;
                    $domain->save();
                }
            });

            $this->redirects->add($oldHost, $newHost, 301, now()->addDays(self::RENAME_REDIRECT_DAYS));
            $this->audit->record('tenant.renamed', ['from' => $oldSlug, 'to' => $newSlug], tenant: $tenant, account: $tenant->account, targetType: Tenant::class, targetId: $tenant->id);
            $this->events->record('tenant.renamed', ['from' => $oldSlug, 'to' => $newSlug], tenant: $tenant, account: $tenant->account);
        });

        $this->hosts->forgetHost($oldHost);
        $this->hosts->forgetTenant($tenant);
        $this->pages->purgeTenant($tenant);

        return $tenant;
    }
}
