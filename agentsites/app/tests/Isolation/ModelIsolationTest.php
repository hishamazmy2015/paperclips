<?php

declare(strict_types=1);

use App\Models\Domain;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Tenancy\Exceptions\MissingTenantContextException;
use App\Tenancy\Exceptions\TenantMismatchException;
use App\Tenancy\TenantContext;

// Spec §8 invariants and §17 / §22.4: no cross-tenant data access, ever — at the model layer.

beforeEach(function (): void {
    $this->a = Tenant::factory()->live()->create(['slug' => 'tenant-a']);
    $this->b = Tenant::factory()->live()->create(['slug' => 'tenant-b']);

    foreach ([$this->a, $this->b] as $tenant) {
        Listing::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        Lead::factory()->create(['tenant_id' => $tenant->id]);
        Media::factory()->create(['tenant_id' => $tenant->id]);
        Testimonial::factory()->create(['tenant_id' => $tenant->id]);
        Domain::factory()->create(['tenant_id' => $tenant->id]);
    }
});

it('refuses a tenant-scoped query when no tenant is bound', function (): void {
    foreach ([Listing::class, Lead::class, Media::class, Testimonial::class, Domain::class] as $model) {
        expect(fn () => $model::query()->count())->toThrow(MissingTenantContextException::class);
    }
});

it('allows cross-tenant reads only inside an explicit global block', function (): void {
    expect(TenantContext::global(fn () => Listing::query()->count()))->toBe(4);
    expect(fn () => Listing::query()->count())->toThrow(MissingTenantContextException::class);
});

it('lets tenant A read only its own rows', function (): void {
    TenantContext::with($this->a, function (): void {
        expect(Listing::query()->count())->toBe(2)
            ->and(Listing::query()->pluck('tenant_id')->unique()->all())->toBe([$this->a->id])
            ->and(Lead::query()->count())->toBe(1)
            ->and(Media::query()->count())->toBe(1)
            ->and(Testimonial::query()->count())->toBe(1)
            ->and(Domain::query()->count())->toBe(1);
    });
});

it('hides tenant B rows from tenant A even by primary key', function (): void {
    $bListing = TenantContext::with($this->b, fn () => Listing::query()->firstOrFail());
    $bLead = TenantContext::with($this->b, fn () => Lead::query()->firstOrFail());

    TenantContext::with($this->a, function () use ($bListing, $bLead): void {
        expect(Listing::query()->find($bListing->id))->toBeNull()
            ->and(Lead::query()->find($bLead->id))->toBeNull()
            ->and(Listing::query()->where('id', $bListing->id)->update(['featured' => true]))->toBe(0)
            ->and(Listing::query()->where('id', $bListing->id)->delete())->toBe(0);
    });

    expect(TenantContext::with($this->b, fn () => Listing::query()->find($bListing->id)?->featured))->toBeFalse();
});

it('fills tenant_id from the bound tenant on create', function (): void {
    $listing = TenantContext::with($this->a, fn () => Listing::query()->create([
        'ref' => 'NEW-1', 'title_en' => 'New', 'offering' => 'sale', 'property_type' => 'apartment', 'price' => 1000000,
    ]));

    expect($listing->tenant_id)->toBe($this->a->id);
});

it('refuses to create a row for another tenant while A is bound', function (): void {
    expect(fn () => TenantContext::with($this->a, fn () => Listing::query()->create([
        'tenant_id' => $this->b->id, 'ref' => 'X-1', 'title_en' => 'X', 'offering' => 'sale', 'property_type' => 'villa', 'price' => 1,
    ])))->toThrow(TenantMismatchException::class);

    expect(TenantContext::with($this->b, fn () => Listing::query()->where('ref', 'X-1')->exists()))->toBeFalse();
});

it('refuses to create a tenant-scoped row with no tenant bound and no global access', function (): void {
    expect(fn () => Lead::query()->create(['tenant_id' => $this->a->id, 'channel' => 'form']))
        ->toThrow(MissingTenantContextException::class);
});

it('never lets a row move to another tenant', function (): void {
    $listing = TenantContext::with($this->a, fn () => Listing::query()->firstOrFail());

    expect(function () use ($listing): void {
        TenantContext::with($this->a, function () use ($listing): void {
            $listing->tenant_id = $this->b->id;
            $listing->save();
        });
    })->toThrow(TenantMismatchException::class);
});

it('scopes relations reached through the tenant model', function (): void {
    TenantContext::with($this->a, function (): void {
        expect($this->a->listings()->count())->toBe(2)
            ->and($this->a->leads()->count())->toBe(1);
    });
});
