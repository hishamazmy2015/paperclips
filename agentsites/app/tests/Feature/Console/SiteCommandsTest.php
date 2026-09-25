<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Listing;
use App\Models\RedirectRule;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\EventPartitions;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Spec §15 CLI and acceptance test A1: `platform:site:create --name --whatsapp` → a live,
// resolvable site, no restart; every command idempotent with --json output.

/** @return array<string, mixed> */
function runJson(string $command, array $args = []): array
{
    Artisan::call($command, $args + ['--json' => true]);

    return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
}

it('creates a live site from a name and a WhatsApp number (A1)', function (): void {
    $out = runJson('platform:site:create', ['--name' => 'Ahmed Al Falasi', '--whatsapp' => '+971501234567']);

    expect($out['ok'])->toBeTrue()
        ->and($out['created'])->toBeTrue()
        ->and($out['slug'])->toBe('ahmed-al-falasi')
        ->and($out['status'])->toBe('live')
        ->and($out['host'])->toBe('ahmed-al-falasi.example.test')
        ->and($out['url'])->toBe('https://ahmed-al-falasi.example.test/')
        ->and($out['preview_url'])->toBeNull();

    // the account + owner user were created for the agent
    $tenant = Tenant::query()->where('slug', 'ahmed-al-falasi')->firstOrFail();
    expect($tenant->account->users()->count())->toBe(1)
        ->and($tenant->account->owner?->phone)->toBe('+971501234567')
        ->and($tenant->account->isTrialing())->toBeTrue();

    // and the site answers on its host, in both languages, with content and demo listings
    $this->get('http://ahmed-al-falasi.example.test/en')->assertOk()->assertSee('Ahmed Al Falasi')->assertSee('Sample listing')->assertHeaderMissing('X-Robots-Tag');
    $this->get('http://ahmed-al-falasi.example.test/ar')->assertOk()->assertSee('dir="rtl"', false)->assertSee('Ahmed Al Falasi');
});

it('is idempotent: the same command again returns the same site and creates nothing', function (): void {
    $first = runJson('platform:site:create', ['--name' => 'Ahmed Al Falasi', '--whatsapp' => '0501234567']);
    $second = runJson('platform:site:create', ['--name' => 'Ahmed Al Falasi', '--whatsapp' => '+971 50 123 4567']);

    expect($second['id'])->toBe($first['id'])
        ->and($second['created'])->toBeFalse()
        ->and(Tenant::query()->count())->toBe(1)
        ->and(Account::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(1);
});

it('creates a draft with --draft and publishes it with platform:site:publish', function (): void {
    $out = runJson('platform:site:create', ['--name' => 'Sara Mansoori', '--whatsapp' => '+971509876543', '--slug' => 'sara', '--theme' => 'atlas', '--locale' => 'ar', '--agency' => 'Mansoori Homes', '--areas' => 'Yas Island,Saadiyat', '--email' => 'sara@example.com', '--draft' => true]);

    expect($out['status'])->toBe('draft')->and($out['preview_url'])->toContain('preview=');
    $this->get('http://sara.example.test/ar')->assertNotFound();
    $this->get('http://sara.example.test/ar?preview='.Tenant::query()->where('slug', 'sara')->firstOrFail()->previewToken())->assertOk()->assertSee('Mansoori Homes');

    $published = runJson('platform:site:publish', ['slug' => 'sara']);
    expect($published['status'])->toBe('live')->and($published['created'])->toBeTrue();
    // the site default (ar) wins when the browser prefers nothing the site has enabled
    $this->get('http://sara.example.test/', ['Accept-Language' => 'fr-FR,fr;q=0.9'])->assertRedirect('/ar');
    $this->get('http://sara.example.test/', ['Accept-Language' => 'en-GB,en;q=0.9'])->assertRedirect('/en');
    $this->get('http://sara.example.test/ar')->assertOk()->assertSee('Yas Island');

    expect(runJson('platform:site:publish', ['slug' => 'sara'])['created'])->toBeFalse();
});

it('accepts a JSON config file', function (): void {
    $out = runJson('platform:site:create', ['--config' => base_path('../samples/agent.json'), '--draft' => true]);

    expect($out['ok'])->toBeTrue()->and($out['slug'])->toBe('ahmed-al-falasi')
        ->and(Tenant::query()->firstOrFail()->mergedConfig()['branding']['palette'])->toBe('navy');
});

it('reports slug conflicts and invalid input as JSON errors with a non-zero exit', function (): void {
    runJson('platform:site:create', ['--name' => 'Ahmed Al Falasi', '--whatsapp' => '+971501234567', '--slug' => 'falasi']);

    $exit = Artisan::call('platform:site:create', ['--name' => 'Someone Else', '--whatsapp' => '+971501111111', '--slug' => 'falasi', '--json' => true]);
    $out = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($exit)->toBe(1)->and($out['ok'])->toBeFalse()->and($out['reason'])->toBe('taken')->and($out['suggestions'])->not->toBeEmpty();

    $exit = Artisan::call('platform:site:create', ['--name' => 'Bad Phone', '--whatsapp' => '12', '--json' => true]);
    expect($exit)->toBe(1)->and(json_decode(Artisan::output(), true)['errors'][0])->toContain('whatsapp');

    $exit = Artisan::call('platform:site:create', ['--name' => 'No Phone', '--json' => true]);
    expect($exit)->toBe(1);
});

it('suspends, restores, renames, deletes and exports a site', function (): void {
    runJson('platform:site:create', ['--name' => 'Ahmed Al Falasi', '--whatsapp' => '+971501234567']);

    expect(runJson('platform:site:suspend', ['slug' => 'ahmed-al-falasi', '--reason' => 'test'])['status'])->toBe('suspended');
    $this->get('http://ahmed-al-falasi.example.test/en')->assertStatus(503);
    expect(runJson('platform:site:suspend', ['slug' => 'ahmed-al-falasi'])['created'])->toBeFalse();

    expect(runJson('platform:site:restore', ['slug' => 'ahmed-al-falasi'])['status'])->toBe('live');
    $this->get('http://ahmed-al-falasi.example.test/en')->assertOk();

    $renamed = runJson('platform:site:rename', ['slug' => 'ahmed-al-falasi', 'new-slug' => 'ahmed']);
    expect($renamed['slug'])->toBe('ahmed')->and($renamed['redirect_from'])->toBe('ahmed-al-falasi.example.test');
    $this->get('http://ahmed-al-falasi.example.test/en/listings?x=1')->assertStatus(301)->assertRedirect('https://ahmed.example.test/en/listings?x=1');
    $this->get('http://ahmed.example.test/en')->assertOk();
    expect(RedirectRule::query()->count())->toBe(1);

    Artisan::call('platform:site:export', ['slug' => 'ahmed']);
    $export = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($export['tenant']['slug'])->toBe('ahmed')->and($export['domains'][0]['host'])->toBe('ahmed.example.test')->and($export['listings'])->toBe([]);

    expect(runJson('platform:site:delete', ['slug' => 'ahmed'])['status'])->toBe('deleted');
    $this->get('http://ahmed.example.test/en')->assertNotFound();
    expect(runJson('platform:site:restore', ['slug' => 'ahmed'])['status'])->toBe('live');

    $exit = Artisan::call('platform:site:publish', ['slug' => 'nope', '--json' => true]);
    expect($exit)->toBe(1);
});

it('clones a site with new identity', function (): void {
    runJson('platform:site:create', ['--name' => 'Ahmed Al Falasi', '--whatsapp' => '+971501234567', '--areas' => 'JVC']);

    $out = runJson('platform:site:clone', ['slug' => 'ahmed-al-falasi', '--to' => 'sara', '--name' => 'Sara Mansoori', '--whatsapp' => '+971509876543']);
    expect($out['slug'])->toBe('sara')->and($out['cloned_from'])->toBe('ahmed-al-falasi')->and($out['status'])->toBe('live');
    $this->get('http://sara.example.test/en')->assertOk()->assertSee('Sara Mansoori')->assertSee('JVC')->assertDontSee('Ahmed Al Falasi');
});

it('reports stats, purges old soft-deleted rows and creates event partitions', function (): void {
    runJson('platform:site:create', ['--name' => 'Ahmed Al Falasi', '--whatsapp' => '+971501234567']);
    runJson('platform:site:create', ['--name' => 'Old Agent', '--whatsapp' => '+971502222222', '--slug' => 'old-agent']);
    runJson('platform:site:delete', ['slug' => 'old-agent']);

    $stats = runJson('platform:stats');
    expect($stats['tenants']['total'])->toBe(1)->and($stats['tenants']['deleted_pending_purge'])->toBe(1)->and($stats['listings']['demo'])->toBe(12);

    expect(runJson('platform:purge', ['--dry-run' => true])['purged']['tenants'])->toBe(0);

    $this->travel(31)->days();
    $purged = runJson('platform:purge');
    expect($purged['purged']['tenants'])->toBe(1)
        ->and(Tenant::withTrashed()->where('slug', 'old-agent')->exists())->toBeFalse()
        ->and(TenantContext::global(fn () => Listing::unscopedByTenant()->count()))->toBe(6);

    $future = now()->addMonths(4);
    $name = EventPartitions::nameFor($future);
    DB::statement("DROP TABLE IF EXISTS {$name}");
    $this->travelTo($future);
    expect(runJson('platform:events:partitions')['created'])->toContain($name)
        ->and(runJson('platform:events:partitions')['created'])->toBe([]);
});
