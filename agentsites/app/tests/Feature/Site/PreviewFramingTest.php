<?php

declare(strict_types=1);

use App\Models\Tenant;

// Spec §17: X-Frame-Options DENY except the owner preview, which only the app host may frame.

it('lets the app host frame a previewed draft and nothing else', function (): void {
    $draft = $this->makeTenant('draft-agent', Tenant::STATUS_DRAFT, ['identity' => ['display_name' => 'Draft Agent']]);
    $live = $this->makeTenant('live-agent', Tenant::STATUS_LIVE, ['identity' => ['display_name' => 'Live Agent']]);

    $this->get('http://draft-agent.example.test/en')->assertNotFound();

    $this->get('http://draft-agent.example.test/en?preview='.$draft->previewToken())
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' http://app.example.test")
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get('http://live-agent.example.test/en')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeaderMissing('Content-Security-Policy');

    $this->get('http://live-agent.example.test/en?preview='.$live->previewToken())
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' http://app.example.test");
});
