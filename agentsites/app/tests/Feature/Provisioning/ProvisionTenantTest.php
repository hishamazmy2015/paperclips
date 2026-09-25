<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Tenant;
use App\Provisioning\CloneTenant;
use App\Provisioning\Exceptions\InvalidProvisionInput;
use App\Provisioning\Exceptions\SlugUnavailable;
use App\Provisioning\ProvisionInput;
use App\Provisioning\ProvisionTenant;
use App\Tenancy\TenantContext;

// Spec §12: the single write path. A config with only a name and a WhatsApp number must
// produce a complete site, idempotently, with no deploy or restart.

beforeEach(function (): void {
    $this->account = Account::factory()->create();
    $this->provision = app(ProvisionTenant::class);
});

function input(int $accountId, array $overrides = []): ProvisionInput
{
    return ProvisionInput::fromArray(array_merge([
        'name' => 'Ahmed Al Falasi',
        'whatsapp' => '+971501234567',
    ], $overrides), $accountId);
}

it('creates a draft tenant with its subdomain host, generated content and demo listings', function (): void {
    $tenant = $this->provision->handle(input($this->account->id, ['agency' => 'Falasi Properties', 'areas' => ['Downtown', 'Marina']]));

    expect($tenant->slug)->toBe('ahmed-al-falasi')
        ->and($tenant->status)->toBe(Tenant::STATUS_DRAFT)
        ->and($tenant->theme_key)->toBe('atlas')
        ->and($tenant->account_id)->toBe($this->account->id);

    $domain = TenantContext::with($tenant, fn () => Domain::query()->firstOrFail());
    expect($domain->host)->toBe('ahmed-al-falasi.example.test')
        ->and($domain->type)->toBe('subdomain')
        ->and($domain->role)->toBe('primary')
        ->and($domain->verified)->toBeTrue()
        ->and($domain->ssl_status)->toBe('issued');

    $config = $tenant->mergedConfig();
    expect($config['identity']['display_name'])->toBe('Ahmed Al Falasi')
        ->and($config['identity']['agency_name'])->toBe('Falasi Properties')
        ->and($config['contact']['whatsapp'])->toBe('+971501234567')
        ->and($config['content']['service_areas'])->toBe(['Downtown', 'Marina'])
        ->and($config['identity']['tagline']['en'])->not->toBe('')
        ->and($config['identity']['tagline']['ar'])->not->toBe('')
        ->and($config['identity']['bio']['en'])->toContain('Ahmed Al Falasi')
        ->and($config['identity']['bio']['ar'])->toContain('Ahmed Al Falasi')
        ->and($config['content']['about']['en'])->toContain('Downtown')
        ->and($config['seo']['meta_description']['ar'])->not->toBe('')
        ->and($config['content']['why_me'])->toHaveCount(3)
        ->and($config['seo']['noindex'])->toBeTrue()
        ->and($tenant->ai_generated_fields)->toHaveKey('identity.tagline.en');

    expect(TenantContext::with($tenant, fn () => Listing::query()->count()))->toBe(6)
        ->and(TenantContext::with($tenant, fn () => Listing::query()->where('source', 'demo')->count()))->toBe(6)
        ->and(TenantContext::with($tenant, fn () => Listing::query()->pluck('community')->unique()->sort()->values()->all()))->toBe(['Downtown', 'Marina']);

    expect(AuditLog::query()->where('action', 'tenant.created')->where('tenant_id', $tenant->id)->exists())->toBeTrue()
        ->and(Event::query()->where('name', 'tenant.created')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('is idempotent on (account, slug)', function (): void {
    $first = $this->provision->handle(input($this->account->id));
    $second = $this->provision->handle(input($this->account->id));

    expect($second->id)->toBe($first->id)
        ->and(Tenant::query()->count())->toBe(1)
        ->and(TenantContext::with($first, fn () => Listing::query()->count()))->toBe(6);
});

it('suffixes a derived slug that another account already took', function (): void {
    $this->provision->handle(input($this->account->id));
    $other = $this->provision->handle(input(Account::factory()->create()->id));

    expect($other->slug)->toBe('ahmed-al-falasi-dubai');
});

it('rejects an explicit slug that is reserved or taken, with suggestions', function (): void {
    expect(fn () => $this->provision->handle(input($this->account->id, ['slug' => 'admin'])))
        ->toThrow(SlugUnavailable::class, 'reserved');

    $this->provision->handle(input($this->account->id, ['slug' => 'falasi']));
    try {
        $this->provision->handle(input(Account::factory()->create()->id, ['slug' => 'falasi']));
        $this->fail('expected SlugUnavailable');
    } catch (SlugUnavailable $e) {
        expect($e->reason)->toBe('taken')->and($e->suggestions)->toHaveCount(3)->and($e->suggestions[0])->toBe('falasi-dubai');
    }
});

it('normalises phones to E.164 and transliterates Arabic names into slugs', function (): void {
    $tenant = $this->provision->handle(input($this->account->id, ['name' => 'سارة المنصوري', 'whatsapp' => '050 123 4567']));

    expect($tenant->slug)->toMatch('/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/')
        ->and($tenant->mergedConfig()['contact']['whatsapp'])->toBe('+971501234567')
        ->and($tenant->displayName())->toBe('سارة المنصوري');
});

it('rejects invalid input before touching the database', function (): void {
    expect(fn () => $this->provision->handle(input($this->account->id, ['whatsapp' => '123'])))
        ->toThrow(InvalidProvisionInput::class, 'whatsapp');
    expect(fn () => $this->provision->handle(input($this->account->id, ['theme' => 'nope'])))
        ->toThrow(InvalidProvisionInput::class, 'theme');
    expect(fn () => $this->provision->handle(input($this->account->id, ['name' => 'A'])))
        ->toThrow(InvalidProvisionInput::class, 'name');
    expect(fn () => $this->provision->handle(input($this->account->id, ['config' => ['identity' => ['years_experience' => 99]]])))
        ->toThrow(InvalidProvisionInput::class);

    expect(Tenant::query()->count())->toBe(0);
});

it('publishes on request: live, indexable, welcome + onboarding events', function (): void {
    $tenant = $this->provision->handle(ProvisionInput::fromArray(['name' => 'Ahmed Al Falasi', 'whatsapp' => '+971501234567'], $this->account->id, publish: true));

    expect($tenant->status)->toBe(Tenant::STATUS_LIVE)
        ->and($tenant->published_at)->not->toBeNull()
        ->and($tenant->onboarding_completed_at)->not->toBeNull()
        ->and($tenant->mergedConfig()['seo']['noindex'])->toBeFalse()
        ->and(Event::query()->where('name', 'published')->where('tenant_id', $tenant->id)->exists())->toBeTrue()
        ->and(Event::query()->where('name', 'onboarding.completed')->where('tenant_id', $tenant->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'tenant.published')->exists())->toBeTrue();
});

it('stores only what was set and merges defaults at read time', function (): void {
    $tenant = $this->provision->handle(input($this->account->id));

    expect($tenant->config)->not->toHaveKey('branding')
        ->and($tenant->mergedConfig()['branding']['palette'])->toBe('sand')
        ->and($tenant->mergedConfig()['locale']['currency'])->toBe('AED')
        ->and($tenant->config['_schema'])->toBe(1);
});

it('accepts the full sample JSON shape', function (): void {
    $data = json_decode((string) file_get_contents(base_path('../samples/agent.json')), true, 512, JSON_THROW_ON_ERROR);
    $tenant = $this->provision->handle(ProvisionInput::fromArray($data, $this->account->id));

    expect($tenant->slug)->toBe('ahmed-al-falasi')
        ->and($tenant->mergedConfig()['branding']['palette'])->toBe('navy')
        ->and($tenant->mergedConfig()['identity']['years_experience'])->toBe(8)
        ->and($tenant->mergedConfig()['contact']['socials']['instagram'])->toBe('https://instagram.com/example');
});

it('clones a site with the source config minus identity and contact', function (): void {
    $source = $this->provision->handle(input($this->account->id, ['areas' => ['JVC'], 'config' => ['branding' => ['palette' => 'gold']]]));

    $clone = app(CloneTenant::class)->handle($source, 'sara-clone', ['name' => 'Sara Mansoori', 'whatsapp' => '+971509876543']);

    expect($clone->slug)->toBe('sara-clone')
        ->and($clone->account_id)->toBe($source->account_id)
        ->and($clone->mergedConfig()['branding']['palette'])->toBe('gold')
        ->and($clone->mergedConfig()['content']['service_areas'])->toBe(['JVC'])
        ->and($clone->mergedConfig()['identity']['display_name'])->toBe('Sara Mansoori')
        ->and($clone->mergedConfig()['identity']['bio']['en'])->toContain('Sara Mansoori')
        ->and($clone->mergedConfig()['contact']['whatsapp'])->toBe('+971509876543');

    expect(fn () => app(CloneTenant::class)->handle($source, 'sara-clone-2', []))->toThrow(InvalidProvisionInput::class);
});
