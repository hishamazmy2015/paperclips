<?php

declare(strict_types=1);

use App\Media\MediaStore;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Tenant;
use App\Models\User;
use App\Provisioning\TenantConfig;
use Illuminate\Support\Facades\Storage;

// Spec §13 S5 and the home page; the media route (spec §17) on both hosts.

beforeEach(function (): void {
    Storage::fake('media');
    $this->user = User::factory()->create();
    $this->tenant = $this->makeTenant('ahmed', Tenant::STATUS_LIVE, ['identity' => ['display_name' => 'Ahmed Al Falasi']]);
    $this->tenant->update(['account_id' => $this->user->account_id, 'onboarding_completed_at' => now()]);
    $this->actingAs($this->user);
});

it('shows the URL, a QR code, share buttons and the checklist percentage', function (): void {
    $this->get('http://app.example.test/onboarding/success')
        ->assertOk()
        ->assertSee('Your website is live')
        ->assertSee('ahmed.example.test')
        ->assertSeeHtml('data-test="qr"')
        ->assertSeeHtml('<svg')
        ->assertSee('Next steps (0% done)')
        ->assertSee('Add listings')
        ->assertSee('Connect your domain');

    Listing::factory()->create(['tenant_id' => $this->tenant->id, 'ref' => 'R-1', 'source' => 'manual']);
    app(TenantConfig::class)->save($this->tenant, array_replace_recursive($this->tenant->config, ['identity' => ['logo' => '/media/x/logo.webp']]));
    $this->get('http://app.example.test/onboarding/success')->assertOk()->assertSee('Next steps (40% done)');
});

it('records share clicks and sends the agent to WhatsApp or the site', function (): void {
    $this->get('http://app.example.test/share/whatsapp')
        ->assertRedirect()
        ->assertRedirectContains('https://wa.me/?text=')
        ->assertRedirectContains(rawurlencode('http://ahmed.example.test/'));
    $this->get('http://app.example.test/share/open')->assertRedirect('http://ahmed.example.test/');
    $this->post('http://app.example.test/share/copy')->assertNoContent();

    expect(Event::query()->where('name', 'share.clicked')->pluck('properties')->map(fn (array $p): string => $p['channel'])->all())->toBe(['whatsapp', 'open', 'copy']);
});

it('sends drafts back to onboarding and shows the home page for live sites', function (): void {
    $this->get('http://app.example.test/home')->assertOk()->assertSee('Live')->assertSee('Open my site');

    $this->tenant->update(['status' => Tenant::STATUS_DRAFT, 'published_at' => null, 'onboarding_step' => 2]);
    $this->get('http://app.example.test/onboarding/success')->assertRedirect('http://app.example.test/onboarding');
});

it('serves tenant media to the owner on the app host and on the tenant host only', function (): void {
    $img = imagecreatetruecolor(4, 4);
    ob_start();
    imagewebp($img);
    $media = app(MediaStore::class)->put($this->tenant, (string) ob_get_clean(), 'photo');
    $path = MediaStore::publicPath($media);
    $other = $this->makeTenant('other', Tenant::STATUS_LIVE);

    $this->get('http://app.example.test'.$path)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    $this->get('http://ahmed.example.test'.$path)->assertOk();
    $this->get('http://other.example.test'.$path)->assertNotFound();
    $this->get('http://app.example.test/media/'.$this->tenant->id.'/missing.webp')->assertNotFound();
    $this->get('http://app.example.test/media/../../etc/passwd')->assertNotFound();

    $this->actingAs(User::factory()->create());
    $this->get('http://app.example.test'.$path)->assertNotFound();
});
