<?php

declare(strict_types=1);

use App\Auth\OtpService;
use App\Mail\SignInCode;
use App\Messaging\Notifier;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\OtpCode;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\Support\SpyNotifier;

// Spec §13 S1 and §17: Google | email code + magic link | WhatsApp OTP; 6 digits, 10 min, 5 attempts,
// per-identifier and per-IP limits; first sign-in creates account + owner + draft site.

const APP = 'http://app.example.test';

beforeEach(function (): void {
    Cache::flush();
    Mail::fake();
    $this->notifier = new SpyNotifier;
    app()->instance(Notifier::class, $this->notifier);
});

function sentCode(): string
{
    $code = '';
    Mail::assertSent(SignInCode::class, function (SignInCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return $code;
}

function magicUrl(): string
{
    $url = '';
    Mail::assertSent(SignInCode::class, function (SignInCode $mail) use (&$url): bool {
        $url = $mail->magicUrl;

        return true;
    });

    return $url;
}

it('shows S1 and records onboarding.started once per session', function (): void {
    // Google is hidden until its keys exist (spec §6: ask for keys, not for choices)
    $this->get(APP.'/start')->assertOk()->assertSee('Send code')->assertDontSee('Continue with Google');
    $this->get(APP.'/start')->assertOk();

    expect(Event::query()->where('name', 'onboarding.started')->count())->toBe(1);
});

it('signs a new agent in with an emailed code and creates account, owner and draft site', function (): void {
    $this->post(APP.'/auth/email', ['email' => 'Ahmed.AlFalasi@example.com'])->assertRedirect(APP.'/start/code');
    $this->get(APP.'/start/code')->assertOk()->assertSee('ahmed.alfalasi@example.com');

    $code = sentCode();
    expect($code)->toMatch('/^\d{6}$/');
    $row = OtpCode::query()->where('identifier', 'ahmed.alfalasi@example.com')->firstOrFail();
    expect($row->code_hash)->not->toContain($code)->and($row->channel)->toBe('email');

    $this->post(APP.'/start/code', ['code' => $code])->assertRedirect(APP.'/onboarding');
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'ahmed.alfalasi@example.com')->firstOrFail();
    expect($user->name)->toBe('Ahmed Alfalasi')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->auth_provider)->toBe('email')
        ->and($user->account->plan_status)->toBe('trialing')
        ->and($user->account->trial_ends_at->diffInDays(now()->addDays(14), true))->toBeLessThan(1)
        ->and($user->account->owner_user_id)->toBe($user->id);

    $tenant = Tenant::query()->where('account_id', $user->account_id)->firstOrFail();
    expect($tenant->isDraft())->toBeTrue()
        ->and($tenant->onboarding_step)->toBe(1)
        ->and($tenant->slug)->toBe('ahmed-alfalasi')
        ->and($tenant->config['identity']['display_name'])->toBe('Ahmed Alfalasi')
        ->and($tenant->config['contact']['whatsapp'] ?? null)->toBeNull()
        ->and($tenant->config['identity']['tagline']['en'])->not->toBe('');
    expect(Event::query()->where('name', 'account.created')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.signed_up')->exists())->toBeTrue()
        ->and(OtpCode::query()->whereNull('consumed_at')->count())->toBe(0);
});

it('rejects wrong codes, burns the code after five attempts and expires it after ten minutes', function (): void {
    $this->post(APP.'/auth/email', ['email' => 'sara@example.com']);
    $code = sentCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    for ($i = 1; $i <= 4; $i++) {
        $this->post(APP.'/start/code', ['code' => $wrong])->assertSessionHasErrors('code');
    }
    expect(OtpCode::query()->first()->attempts)->toBe(4);
    $this->post(APP.'/start/code', ['code' => $wrong])->assertSessionHasErrors('code');
    // the fifth wrong attempt consumed the code: even the right one no longer works
    $this->post(APP.'/start/code', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();

    Mail::fake();
    Cache::flush();
    $this->post(APP.'/auth/email', ['email' => 'sara@example.com']);
    $fresh = sentCode();
    $this->travel(11)->minutes();
    $this->post(APP.'/start/code', ['code' => $fresh])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('accepts Arabic-Indic digits and limits how often a code is sent', function (): void {
    $this->post(APP.'/auth/email', ['email' => 'noor@example.com']);
    $code = sentCode();
    $arabic = strtr($code, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    $this->post(APP.'/start/code', ['code' => $arabic])->assertRedirect(APP.'/onboarding');
    $this->assertAuthenticated();

    auth()->logout();
    for ($i = 0; $i < OtpService::SENDS_PER_IDENTIFIER - 1; $i++) {
        $this->post(APP.'/auth/email', ['email' => 'noor@example.com'])->assertRedirect(APP.'/start/code');
    }
    $this->post(APP.'/auth/email', ['email' => 'noor@example.com'])->assertSessionHasErrors('email');
});

it('signs in through the magic link exactly once', function (): void {
    $this->post(APP.'/auth/email', ['email' => 'omar@example.com']);
    $url = magicUrl();
    expect($url)->toStartWith(APP.'/auth/magic/');

    $this->get($url)->assertRedirect(APP.'/onboarding');
    $this->assertAuthenticated();

    auth()->logout();
    $this->get($url)->assertRedirect(APP.'/start');
    $this->assertGuest();

    $this->get(APP.'/auth/magic/999999?signature=bad')->assertForbidden();
});

it('signs in with a WhatsApp code and prefills the draft with the verified number', function (): void {
    $this->post(APP.'/auth/phone', ['phone' => '050 123 4567'])->assertRedirect(APP.'/start/code');
    expect($this->notifier->sent)->toHaveCount(1)
        ->and($this->notifier->sent[0]['to'])->toBe('+971501234567')
        ->and($this->notifier->lastCode())->toMatch('/^\d{6}$/');

    $this->post(APP.'/start/code', ['code' => $this->notifier->lastCode()])->assertRedirect(APP.'/onboarding');
    $user = User::query()->where('phone', '+971501234567')->firstOrFail();
    expect($user->phone_verified_at)->not->toBeNull()->and($user->auth_provider)->toBe('whatsapp')->and($user->name)->toBe('Agent');
    $tenant = Tenant::query()->where('account_id', $user->account_id)->firstOrFail();
    expect($tenant->config['contact']['whatsapp'])->toBe('+971501234567');
});

it('reports an invalid phone and a WhatsApp delivery failure without issuing a code', function (): void {
    $this->post(APP.'/auth/phone', ['phone' => '12'])->assertSessionHasErrors('phone');
    $this->notifier->accept = false;
    $this->post(APP.'/auth/phone', ['phone' => '+971509999999'])->assertSessionHasErrors('phone');
    expect(OtpCode::query()->count())->toBe(1);
});

it('signs in with Google and links the Google id to an existing email account', function (): void {
    config(['services.google.client_id' => 'client', 'services.google.client_secret' => 'secret']);
    $existing = User::factory()->create(['email' => 'lina@example.com', 'google_id' => null]);
    Tenant::factory()->for($existing->account)->create();

    $google = (new GoogleUser)->map(['id' => 'g-123', 'name' => 'Lina Haddad', 'email' => 'lina@example.com']);
    $provider = Mockery::mock();
    $provider->shouldReceive('user')->andReturn($google);
    $provider->shouldReceive('redirect')->andReturn(redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?client_id=client'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(APP.'/auth/google/callback')->assertRedirect(APP.'/onboarding');
    $this->assertAuthenticatedAs($existing);
    expect($existing->fresh()->google_id)->toBe('g-123')
        ->and(Account::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'user.signed_in')->exists())->toBeTrue();

    $this->get(APP.'/auth/google')->assertRedirectContains('accounts.google.com');
});

it('keeps Google off and guests out until configured', function (): void {
    $this->get(APP.'/auth/google')->assertRedirect(APP.'/start');
    $this->get(APP.'/onboarding')->assertRedirect(APP.'/start');
    $this->get(APP.'/home')->assertRedirect(APP.'/start');
    $this->get(APP.'/start/code')->assertRedirect(APP.'/start');
});

it('signs out', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->post(APP.'/logout')->assertRedirect(APP);
    $this->assertGuest();
});

it('switches the UI language with ?lang and remembers it', function (): void {
    $this->get(APP.'/start?lang=ar')->assertOk()->assertSee('lang="ar" dir="rtl"', false)->assertSee('أرسل الرمز')->assertHeader('Content-Language', 'ar');
    $this->get(APP.'/start')->assertOk()->assertSee('lang="ar" dir="rtl"', false);
    $this->get(APP.'/start?lang=en')->assertOk()->assertSee('lang="en" dir="ltr"', false);
});
