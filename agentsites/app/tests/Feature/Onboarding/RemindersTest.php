<?php

declare(strict_types=1);

use App\Auth\SignIn;
use App\Jobs\SendOnboardingReminders;
use App\Mail\OnboardingReminder;
use App\Messaging\Notifier;
use App\Models\Event;
use App\Models\User;
use App\Platform\EventLog;
use App\Provisioning\PublishTenant;
use App\Provisioning\TenantConfig;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Support\SpyNotifier;

// Spec §13 abandonment: +1 h and +24 h, email + WhatsApp when verified, opt-out link, never twice.

beforeEach(function (): void {
    Mail::fake();
    $this->notifier = new SpyNotifier;
    app()->instance(Notifier::class, $this->notifier);
    $this->user = User::factory()->create(['email' => 'ahmed@example.com', 'phone' => '+971501234567', 'phone_verified_at' => now()]);
    $this->user->account->update(['owner_user_id' => $this->user->id]);
    $this->tenant = app(SignIn::class)->draft($this->user, 'en');
});

it('reminds once after an hour and once after a day, by email and WhatsApp, with a resume link', function (): void {
    expect(app(SendOnboardingReminders::class)->handle($this->notifier, app(EventLog::class)))->toBe(0);

    $this->travel(61)->minutes();
    expect(app(SendOnboardingReminders::class)->handle($this->notifier, app(EventLog::class)))->toBe(1);
    Mail::assertSent(OnboardingReminder::class, fn (OnboardingReminder $mail): bool => $mail->hasTo('ahmed@example.com') && $mail->kind === '1h' && str_contains($mail->resumeUrl, '/onboarding?step=1') && str_contains($mail->optOutUrl, '/reminders/opt-out'));
    expect($this->notifier->sent)->toHaveCount(1)->and($this->notifier->sent[0]['text'])->toContain('/onboarding?step=1')
        ->and($this->tenant->fresh()->reminder_1h_sent_at)->not->toBeNull()
        ->and(Event::query()->where('name', 'reminder.sent')->first()->properties)->toBe(['kind' => '1h', 'channels' => ['email', 'whatsapp']]);

    // nothing more until the 24 h mark
    expect(app(SendOnboardingReminders::class)->handle($this->notifier, app(EventLog::class)))->toBe(0);
    $this->travel(24)->hours();
    expect(app(SendOnboardingReminders::class)->handle($this->notifier, app(EventLog::class)))->toBe(1);
    Mail::assertSent(OnboardingReminder::class, 2);
    expect(app(SendOnboardingReminders::class)->handle($this->notifier, app(EventLog::class)))->toBe(0);
});

it('skips published sites and agents who opted out', function (): void {
    $this->travel(2)->hours();
    $this->get(URL::signedRoute('reminders.opt-out', ['user' => $this->user->id]))->assertOk()->assertSee('Reminders stopped');
    expect($this->user->fresh()->reminders_opted_out_at)->not->toBeNull();
    $this->get('http://app.example.test/reminders/opt-out?user='.$this->user->id)->assertForbidden();

    expect(app(SendOnboardingReminders::class)->handle($this->notifier, app(EventLog::class)))->toBe(0);
    Mail::assertNothingSent();

    $other = User::factory()->create();
    $other->account->update(['owner_user_id' => $other->id]);
    $tenant = app(SignIn::class)->draft($other, 'ar');
    app(TenantConfig::class)->save($tenant, array_replace_recursive($tenant->config, ['contact' => ['whatsapp' => '+971509999999']]));
    app(PublishTenant::class)->handle($tenant->fresh());
    $this->travel(2)->hours();
    expect(app(SendOnboardingReminders::class)->handle($this->notifier, app(EventLog::class)))->toBe(0);
});
