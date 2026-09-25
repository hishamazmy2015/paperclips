<?php

declare(strict_types=1);

use App\Models\Domain;
use App\Models\RedirectRule;
use App\Models\Tenant;
use App\Provisioning\Exceptions\SlugUnavailable;
use App\Provisioning\Slugs;
use App\Provisioning\TenantLifecycle;
use App\Tenancy\Exceptions\SlugImmutableException;
use App\Tenancy\HostCache;
use App\Tenancy\Resolution;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Illuminate\Support\Facades\Cache;

// Spec §8 invariants + §15 lifecycle commands: every change is data, audited and cache-safe.

beforeEach(function (): void {
    $this->tenant = $this->makeTenant('ahmed');
    $this->lifecycle = app(TenantLifecycle::class);
    $this->resolver = app(TenantResolver::class);
});

it('suspends and restores, invalidating the host cache each time', function (): void {
    $host = $this->tenant->subdomainHost();
    expect($this->resolver->resolve($host)->entry['status'])->toBe('live')
        ->and(Cache::has(HostCache::key($host)))->toBeTrue();

    $this->lifecycle->suspend($this->tenant, 'abuse');
    expect(Cache::has(HostCache::key($host)))->toBeFalse()
        ->and($this->resolver->resolve($host)->entry['status'])->toBe('suspended');

    $this->lifecycle->restore($this->tenant);
    expect($this->resolver->resolve($host)->entry['status'])->toBe('live');
});

it('restores a never-published tenant to draft', function (): void {
    $draft = $this->makeTenant('draft-one', Tenant::STATUS_DRAFT);
    $this->lifecycle->suspend($draft);
    expect($this->lifecycle->restore($draft)->status)->toBe('draft');
});

it('soft-deletes the tenant and its domains; the host stops resolving until restored', function (): void {
    $host = $this->tenant->subdomainHost();
    $this->resolver->resolve($host);
    $this->lifecycle->delete($this->tenant);

    expect(Tenant::query()->find($this->tenant->id))->toBeNull()
        ->and(Tenant::withTrashed()->find($this->tenant->id)?->status)->toBe('deleted')
        ->and(TenantContext::global(fn () => Domain::unscopedByTenant()->where('host', $host)->exists()))->toBeFalse()
        ->and(TenantContext::global(fn () => Domain::unscopedByTenant()->withTrashed()->where('host', $host)->exists()))->toBeTrue()
        ->and($this->resolver->resolve($host)->kind)->toBe(Resolution::UNKNOWN)
        ->and(app(Slugs::class)->reason('ahmed'))->toBe('taken');

    $this->lifecycle->restore(Tenant::withTrashed()->findOrFail($this->tenant->id));
    expect($this->resolver->resolve($host)->kind)->toBe(Resolution::TENANT)
        ->and($this->resolver->resolve($host)->entry['status'])->toBe('live');
});

it('renames a site through a 301 redirect rule and keeps the old slug unavailable', function (): void {
    $oldHost = $this->tenant->subdomainHost();
    $this->resolver->resolve($oldHost);

    $this->lifecycle->rename($this->tenant, 'ahmed-al-falasi');

    expect($this->tenant->fresh()?->slug)->toBe('ahmed-al-falasi')
        ->and(TenantContext::global(fn () => Domain::unscopedByTenant()->where('tenant_id', $this->tenant->id)->value('host')))->toBe('ahmed-al-falasi.example.test');

    $rule = RedirectRule::query()->where('from_host', $oldHost)->firstOrFail();
    expect($rule->to_host)->toBe('ahmed-al-falasi.example.test')
        ->and($rule->status_code)->toBe(301)
        ->and($rule->expires_at?->isAfter(now()->addDays(89)))->toBeTrue();

    $old = $this->resolver->resolve($oldHost);
    expect($old->kind)->toBe(Resolution::REDIRECT)->and($old->redirectHost)->toBe('ahmed-al-falasi.example.test');
    expect($this->resolver->resolve('ahmed-al-falasi.example.test')->kind)->toBe(Resolution::TENANT);

    expect(fn () => $this->lifecycle->rename($this->makeTenant('other'), 'ahmed'))->toThrow(SlugUnavailable::class, 'taken');
});

it('keeps the slug immutable after publish except through rename', function (): void {
    expect(function (): void {
        $this->tenant->slug = 'hacked';
        $this->tenant->save();
    })->toThrow(SlugImmutableException::class);

    $draft = $this->makeTenant('draft-two', Tenant::STATUS_DRAFT);
    $draft->slug = 'draft-three';
    $draft->save();
    expect($draft->fresh()?->slug)->toBe('draft-three');
});
