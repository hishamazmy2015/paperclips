<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Domain;
use App\Models\Tenant;
use App\Provisioning\Exceptions\CannotPublish;
use App\Provisioning\Exceptions\InvalidProvisionInput;
use App\Provisioning\Exceptions\SlugUnavailable;
use App\Provisioning\ProvisionInput;
use App\Provisioning\ProvisionTenant;
use App\Provisioning\PublishTenant;
use App\Provisioning\TenantConfig;
use App\Provisioning\TenantLifecycle;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;

// Spec §13 S1: the draft exists before the agent has typed anything; §12: it cannot go live
// until the two facts a site needs are there; the slug is free while it is a draft.

beforeEach(function (): void {
    $this->account = Account::factory()->create();
});

it('creates a partial draft without WhatsApp and refuses to publish it until it has one', function (): void {
    $tenant = app(ProvisionTenant::class)->handle(new ProvisionInput($this->account->id, 'Ahmed', '', partial: true));

    expect($tenant->isDraft())->toBeTrue()
        ->and($tenant->config['contact']['whatsapp'] ?? null)->toBeNull()
        ->and($tenant->config['identity']['tagline']['ar'])->not->toBe('')
        ->and(PublishTenant::missingForPublish($tenant))->toBe(['contact.whatsapp']);

    expect(fn () => app(PublishTenant::class)->handle($tenant))->toThrow(CannotPublish::class, 'contact.whatsapp');
    Artisan::call('platform:site:publish', ['slug' => 'ahmed', '--json' => true]);
    expect(json_decode(Artisan::output(), true))->toMatchArray(['ok' => false, 'missing' => ['contact.whatsapp']]);

    app(TenantConfig::class)->save($tenant, array_replace_recursive($tenant->config, ['contact' => ['whatsapp' => '+971501234567']]));
    expect(app(PublishTenant::class)->handle($tenant->fresh())->isLive())->toBeTrue();
});

it('still rejects an invalid or missing WhatsApp number outside onboarding, and publish with partial', function (): void {
    expect(fn () => app(ProvisionTenant::class)->handle(new ProvisionInput($this->account->id, 'Ahmed', '')))->toThrow(InvalidProvisionInput::class, 'whatsapp');
    expect(fn () => app(ProvisionTenant::class)->handle(new ProvisionInput($this->account->id, 'Ahmed', '12', partial: true)))->toThrow(InvalidProvisionInput::class, 'whatsapp');
    expect(fn () => app(ProvisionTenant::class)->handle(new ProvisionInput($this->account->id, 'Ahmed', '', publish: true, partial: true)))->toThrow(InvalidProvisionInput::class, 'partial');
});

it('changes a draft slug with its host row, and never a published one', function (): void {
    $draft = app(ProvisionTenant::class)->handle(new ProvisionInput($this->account->id, 'Ahmed', '+971501234567'));
    $this->makeTenant('taken', Tenant::STATUS_LIVE);

    expect(fn () => app(TenantLifecycle::class)->changeDraftSlug($draft, 'taken'))->toThrow(SlugUnavailable::class);
    app(TenantLifecycle::class)->changeDraftSlug($draft, 'ahmed-homes');
    $hosts = TenantContext::global(fn () => Domain::unscopedByTenant()->where('tenant_id', $draft->id)->pluck('host')->all());
    expect($draft->fresh()->slug)->toBe('ahmed-homes')->and($hosts)->toBe(['ahmed-homes.example.test']);
    expect(app(TenantLifecycle::class)->changeDraftSlug($draft, 'ahmed-homes')->slug)->toBe('ahmed-homes');

    app(PublishTenant::class)->handle($draft->fresh());
    expect(fn () => app(TenantLifecycle::class)->changeDraftSlug($draft->fresh(), 'ahmed-live'))->toThrow(SlugUnavailable::class, 'published');
});

it('regenerates template copy when the facts change but leaves typed copy alone', function (): void {
    $tenant = app(ProvisionTenant::class)->handle(new ProvisionInput($this->account->id, 'Placeholder Name', '+971501234567'));
    expect($tenant->config['identity']['bio']['en'])->toContain('Placeholder Name');

    $config = $tenant->config;
    $config['identity']['display_name'] = 'Sara Mansoori';
    $config['identity']['bio']['en'] = 'My own words.';
    $marks = $tenant->ai_generated_fields;
    unset($marks['identity.bio.en']);
    $tenant->ai_generated_fields = $marks;
    app(TenantConfig::class)->save($tenant, $config);

    app(ProvisionTenant::class)->refreshTemplateContent($tenant);
    $fresh = $tenant->fresh();
    expect($fresh->config['identity']['bio']['ar'])->toContain('Sara Mansoori')
        ->and($fresh->config['identity']['bio']['en'])->toBe('My own words.')
        ->and($fresh->config['content']['about']['en'])->toContain('Sara Mansoori')
        ->and($fresh->config['seo']['meta_description']['en'])->not->toContain('Placeholder Name');
});
