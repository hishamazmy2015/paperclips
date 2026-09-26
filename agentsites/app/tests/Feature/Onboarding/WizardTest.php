<?php

declare(strict_types=1);

use App\Auth\SignIn;
use App\Livewire\Onboarding\Wizard;
use App\Messaging\Notifier;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use App\Provisioning\TenantConfig;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\SpyNotifier;

// Spec §13 S2–S4: autosave on every change into the draft created at sign-in, live slug check with
// suggestions, theme + palette, area chips, the real preview, publish → live. Events on every step.

beforeEach(function (): void {
    Storage::fake('media');
    Storage::fake('local');
    $this->notifier = new SpyNotifier;
    app()->instance(Notifier::class, $this->notifier);

    $this->user = User::factory()->create(['email' => 'ahmed@example.com', 'name' => 'Ahmed', 'phone' => null]);
    $this->tenant = app(SignIn::class)->draft($this->user, 'en');
    $this->actingAs($this->user);
});

function events(string $name): array
{
    return Event::query()->where('name', $name)->orderBy('id')->get()->map(fn (Event $e): array => (array) $e->properties)->all();
}

it('autosaves S2 into the draft and only lets a complete step continue', function (): void {
    $component = Livewire::test(Wizard::class)
        ->assertSet('step', 1)
        ->assertSet('name', 'Ahmed')
        ->assertSeeHtml('data-test="whatsapp"')
        ->assertSeeHtml('data-test="next"');
    expect(events('step.viewed'))->toEqual([['n' => 1]]);

    $component->set('name', 'Ahmed Al Falasi')->set('agency', 'Falasi Properties');
    $stored = $this->tenant->fresh();
    expect($stored->config['identity']['display_name'])->toBe('Ahmed Al Falasi')
        ->and($stored->config['identity']['agency_name'])->toBe('Falasi Properties')
        ->and($stored->config['content']['about']['en'])->toContain('Ahmed Al Falasi')
        ->and($stored->config['identity']['bio']['en'])->toContain('Falasi Properties')
        ->and($this->user->fresh()->name)->toBe('Ahmed Al Falasi')
        ->and($stored->config_version)->toBeGreaterThan(1);

    // no WhatsApp yet → cannot continue
    $component->call('next')->assertSet('step', 1);

    $component->set('whatsapp', '12')->assertSet('whatsappState', 'invalid');
    expect($this->tenant->fresh()->config['contact']['whatsapp'] ?? null)->toBeNull();

    $component->set('whatsapp', '050 123 4567')->assertSet('whatsappState', 'ok')->assertSet('whatsapp', '+971501234567');
    expect($this->tenant->fresh()->config['contact']['whatsapp'])->toBe('+971501234567');

    $component->set('license', 'BRN 12345')->call('next')->assertSet('step', 2);
    expect($this->tenant->fresh()->onboarding_step)->toBe(2)
        ->and($this->tenant->fresh()->config['identity']['license_no'])->toBe('BRN 12345')
        ->and(events('step.completed'))->toEqual([['n' => 1]])
        ->and(events('step.viewed'))->toEqual([['n' => 1], ['n' => 2]]);
});

it('checks the subdomain live, suggests alternatives and moves the host row on Next', function (): void {
    $this->makeTenant('sara', Tenant::STATUS_LIVE);
    $this->tenant->update(['onboarding_step' => 2]);

    $component = Livewire::test(Wizard::class, ['step' => 2])
        ->assertSet('step', 2)
        ->assertSet('slug', 'ahmed')
        ->assertSet('slugState', 'available')
        ->assertSee('https://ahmed.example.test');

    $component->set('slug', 'sara')->assertSet('slugState', 'taken');
    expect($component->get('suggestions'))->toContain('sara-dubai')
        ->and(events('slug.checked'))->toEqual([['available' => false, 'slug' => 'sara']]);

    $component->set('slug', 'Admin')->assertSet('slugState', 'reserved')->assertSet('slug', 'admin');
    $component->set('slug', 'a b')->assertSet('slugState', 'invalid');
    $component->call('next')->assertSet('step', 2);

    $component->call('useSuggestion', 'sara-dubai')->assertSet('slug', 'sara-dubai')->assertSet('slugState', 'available');
    $component->set('slug', 'ahmed-al-falasi')->assertSet('slugState', 'available')->call('next')->assertSet('step', 3);

    $tenant = $this->tenant->fresh();
    expect($tenant->slug)->toBe('ahmed-al-falasi')->and($tenant->onboarding_step)->toBe(3);
    $hosts = TenantContext::global(fn () => Domain::unscopedByTenant()->where('tenant_id', $tenant->id)->pluck('host')->all());
    expect($hosts)->toBe(['ahmed-al-falasi.example.test']);
});

it('selects any of the three themes and a palette; unknown themes are ignored', function (): void {
    $this->tenant->update(['onboarding_step' => 2]);

    $component = Livewire::test(Wizard::class, ['step' => 2])
        ->assertSeeHtml('data-test="theme-atlas"')
        ->assertSeeHtml('data-test="theme-marina"')
        ->assertSeeHtml('data-test="theme-palm"')
        ->assertDontSee('coming soon');

    $component->call('selectTheme', 'nope')->assertSet('theme', 'atlas');
    $component->call('selectTheme', 'marina')->assertSet('theme', 'marina');
    expect($this->tenant->fresh()->theme_key)->toBe('marina');
    $component->call('selectTheme', 'atlas')->assertSet('theme', 'atlas');
    expect(events('theme.selected'))->toEqual([['theme' => 'marina'], ['theme' => 'atlas']]);

    $component->call('selectPalette', 'navy')->assertSet('palette', 'navy');
    expect($this->tenant->fresh()->config['branding']['palette'])->toBe('navy');
    $component->call('selectPalette', 'neon')->assertSet('palette', 'navy');
});

it('stores a square WebP photo and derives a custom palette from a logo', function (): void {
    $component = Livewire::test(Wizard::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg', 900, 600));

    $photo = (string) $component->get('photoUrl');
    expect($photo)->toStartWith('/media/'.$this->tenant->id.'/photo-')->toEndWith('.webp')
        ->and($this->tenant->fresh()->config['identity']['photo'])->toBe($photo);
    $file = substr($photo, strlen('/media/'));
    expect(Storage::disk('media')->exists($file))->toBeTrue();
    $info = getimagesizefromstring((string) Storage::disk('media')->get($file));
    expect($info[0])->toBe(512)->and($info[1])->toBe(512)->and($info['mime'])->toBe('image/webp');

    // a navy logo → custom palette with a navy-ish primary
    $img = imagecreatetruecolor(200, 80);
    imagefill($img, 0, 0, imagecolorallocate($img, 30, 58, 138));
    ob_start();
    imagepng($img);
    $png = (string) ob_get_clean();
    $this->tenant->update(['onboarding_step' => 2]);

    $component = Livewire::test(Wizard::class, ['step' => 2])->set('logo', UploadedFile::fake()->createWithContent('logo.png', $png));
    expect($component->get('palette'))->toBe('custom')
        ->and($component->get('customPalette')['primary'])->toMatch('/^#[0-9a-f]{6}$/')
        ->and($this->tenant->fresh()->config['branding']['palette'])->toBe('custom')
        ->and($this->tenant->fresh()->config['identity']['logo'])->toStartWith('/media/');

    $component->set('logo', UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'));
    expect($component->get('logoError'))->not->toBe('');
});

it('toggles area chips, shows the real preview and publishes the site', function (): void {
    $this->tenant->update(['onboarding_step' => 3]);
    app(TenantConfig::class)->save($this->tenant, array_replace_recursive($this->tenant->config, ['contact' => ['whatsapp' => '+971501234567']]));

    $component = Livewire::test(Wizard::class, ['step' => 3])
        ->assertSet('step', 3)
        ->assertSeeHtml('data-test="preview"')
        ->assertSeeHtml('preview='.$this->tenant->previewToken())
        ->assertSeeHtml('data-test="publish"');

    $component->call('toggleArea', 'Downtown')->call('toggleArea', 'Marina')->call('toggleArea', 'Nowhere');
    expect($component->get('areas'))->toBe(['Downtown', 'Marina'])
        ->and($this->tenant->fresh()->config['content']['service_areas'])->toBe(['Downtown', 'Marina'])
        ->and($this->tenant->fresh()->config['identity']['tagline']['en'])->toContain('Downtown');
    $component->call('toggleArea', 'Downtown');
    expect($component->get('areas'))->toBe(['Marina']);

    $component->call('publish')->assertRedirect(route('onboarding.success'));

    $tenant = $this->tenant->fresh();
    expect($tenant->isLive())->toBeTrue()
        ->and($tenant->onboarding_completed_at)->not->toBeNull()
        ->and($tenant->config['locale']['default'])->toBe('en')
        ->and($tenant->mergedConfig()['seo']['noindex'])->toBeFalse()
        ->and(events('published'))->toEqual([['first' => true]])
        ->and($this->notifier->sent)->toHaveCount(1)
        ->and($this->notifier->sent[0]['to'])->toBe('+971501234567');

    $this->get('http://ahmed.example.test/en')->assertOk()->assertSee('Ahmed');
});

it('refuses to publish without a WhatsApp number and sends the agent back to step 1', function (): void {
    $this->tenant->update(['onboarding_step' => 3]);

    Livewire::test(Wizard::class, ['step' => 3])
        ->call('publish')
        ->assertSet('step', 1)
        ->assertSet('publishError', __('platform.wizard.cannot_publish'));
    expect($this->tenant->fresh()->isDraft())->toBeTrue();
});

it('resumes at the last step, never beyond it, and can finish later', function (): void {
    $this->tenant->update(['onboarding_step' => 2]);

    // no ?step → the last step reached; ?step beyond it is clamped; query params stick to the test request, so the plain case goes first
    Livewire::test(Wizard::class)->assertSet('step', 2)->call('back')->assertSet('step', 1)->call('finishLater')->assertRedirect(route('home'));
    Livewire::withQueryParams(['step' => 3])->test(Wizard::class)->assertSet('step', 2);
    Livewire::withQueryParams(['step' => 1])->test(Wizard::class)->assertSet('step', 1)->call('back')->assertSet('step', 1);

    $this->get('http://app.example.test/home')->assertOk()->assertSee('Continue setting up')->assertSee('Draft');
    $this->get('http://app.example.test/onboarding')->assertOk()->assertSee('Your website');
});

it('renders right-to-left in Arabic with at most six typed fields across the steps', function (): void {
    $page = $this->get('http://app.example.test/onboarding?lang=ar')->assertOk()->assertSee('lang="ar" dir="rtl"', false)->assertSee('عن نفسك');
    $inputsStep1 = preg_match_all('/<input[^>]+type="(text|tel|email)"/', $page->getContent());
    $this->tenant->update(['onboarding_step' => 2]);
    $page2 = $this->get('http://app.example.test/onboarding?step=2')->assertOk();
    $inputsStep2 = preg_match_all('/<input[^>]+type="(text|tel|email)"/', $page2->getContent());

    expect($inputsStep1 + $inputsStep2)->toBeLessThanOrEqual(5); // + the email at S1 = 6 (spec §13)
});
