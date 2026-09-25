<?php

declare(strict_types=1);

use App\Models\Domain;
use App\Tenancy\HostCache;
use App\Tenancy\RedirectRules;
use App\Tenancy\Resolution;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Spec §11 steps 1–4 as pure resolution (the middleware turns these into responses).

beforeEach(function (): void {
    $this->tenant = $this->makeTenant('ahmed');
    $this->resolver = app(TenantResolver::class);
});

it('skips platform and legacy hosts', function (): void {
    config(['platform.legacy_hosts' => ['legacy.example.test']]);

    foreach (['example.test', 'www.example.test', 'app.example.test', 'ADMIN.example.test:8444', 'api.example.test', 'legacy.example.test'] as $host) {
        expect($this->resolver->resolve($host)->kind)->toBe(Resolution::PLATFORM, $host);
    }
});

it('resolves a tenant subdomain and serves repeat lookups from cache without a query', function (): void {
    $first = $this->resolver->resolve('Ahmed.example.test:8444');
    expect($first->kind)->toBe(Resolution::TENANT)
        ->and($first->entry['tenant_id'])->toBe($this->tenant->id)
        ->and($first->entry['slug'])->toBe('ahmed')
        ->and($first->entry['status'])->toBe('live')
        ->and($first->entry['role'])->toBe('primary')
        ->and($first->entry['primary_host'])->toBe('ahmed.example.test')
        ->and($first->entry['theme_key'])->toBe('atlas');

    DB::enableQueryLog();
    $second = $this->resolver->resolve('ahmed.example.test');
    expect(DB::getQueryLog())->toBeEmpty()->and($second->entry)->toBe($first->entry);
});

it('returns unknown for hosts nobody owns and caches the miss briefly', function (): void {
    expect($this->resolver->resolve('nobody.example.test')->kind)->toBe(Resolution::UNKNOWN)
        ->and(Cache::get(HostCache::key('nobody.example.test')))->toBe('__miss__');
});

it('redirects an alias host to the primary host', function (): void {
    TenantContext::with($this->tenant, fn () => Domain::query()->create(['host' => 'www.ahmedhomes.ae', 'type' => 'custom', 'role' => 'alias', 'verified' => true]));

    $res = $this->resolver->resolve('www.ahmedhomes.ae');
    expect($res->kind)->toBe(Resolution::REDIRECT)
        ->and($res->redirectHost)->toBe('ahmed.example.test')
        ->and($res->redirectStatus)->toBe(301);
});

it('applies redirect rules before host lookup and honours their expiry', function (): void {
    $rules = app(RedirectRules::class);
    $rules->add('old.example.test', 'ahmed.example.test', 301, now()->addDay());
    expect($this->resolver->resolve('old.example.test')->kind)->toBe(Resolution::REDIRECT);

    $rules->add('expired.example.test', 'ahmed.example.test', 301, now()->subMinute());
    expect($this->resolver->resolve('expired.example.test')->kind)->toBe(Resolution::UNKNOWN)
        ->and($rules->purgeExpired())->toBe(1);
});

it('drops every cached host of a tenant on forgetTenant', function (): void {
    $this->resolver->resolve('ahmed.example.test');
    expect(Cache::has(HostCache::key('ahmed.example.test')))->toBeTrue();

    app(HostCache::class)->forgetTenant($this->tenant);
    expect(Cache::has(HostCache::key('ahmed.example.test')))->toBeFalse();
});
