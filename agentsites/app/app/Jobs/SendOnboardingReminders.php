<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OnboardingReminder;
use App\Messaging\Notifier;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\EventLog;
use App\Platform\Hosts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Abandonment reminders (spec §13): a draft whose onboarding stalled gets one nudge after 1 h
 * and one after 24 h — email, plus WhatsApp when the phone is verified — unless the owner
 * opted out. Runs every 15 minutes from the scheduler; each reminder is sent at most once.
 */
final class SendOnboardingReminders implements ShouldQueue
{
    use Queueable;

    public function handle(Notifier $notifier, EventLog $events): int
    {
        $sent = 0;
        /** @var array<string, int> $rules */
        $rules = config('onboarding.reminders', ['1h' => 60, '24h' => 1440]);

        foreach ($rules as $kind => $minutes) {
            $column = 'reminder_'.$kind.'_sent_at';
            $tenants = Tenant::query()
                ->where('status', Tenant::STATUS_DRAFT)
                ->whereNull('onboarding_completed_at')
                ->whereNull($column)
                ->where('onboarding_step', '>', 0)
                ->where('updated_at', '<=', now()->subMinutes($minutes))
                ->with('account.owner')
                ->orderBy('id')
                ->limit(500)
                ->get();

            foreach ($tenants as $tenant) {
                $owner = $tenant->account->owner;
                if ($owner === null || $owner->reminders_opted_out_at !== null) {
                    $tenant->setAttribute($column, now());
                    $tenant->saveQuietly();

                    continue;
                }
                $channels = $this->send($tenant, $owner, $kind, $notifier);
                $tenant->setAttribute($column, now());
                $tenant->saveQuietly(); // not a "change" the next reminder should count from
                $events->record('reminder.sent', ['kind' => $kind, 'channels' => $channels], tenant: $tenant, account: $tenant->account, user: $owner);
                $sent++;
            }
        }

        return $sent;
    }

    /** @return list<string> */
    private function send(Tenant $tenant, User $owner, string $kind, Notifier $notifier): array
    {
        $locale = (string) ($tenant->account->locale ?? config('platform.default_locale'));
        $resume = Hosts::browserUrl(Hosts::app(), '/onboarding?step='.max(1, $tenant->onboarding_step));
        $channels = [];

        if ($owner->email !== null) {
            try {
                $optOut = URL::signedRoute('reminders.opt-out', ['user' => $owner->id]);
                Mail::to($owner->email)->locale($locale)->send(new OnboardingReminder($owner->name, $resume, $optOut, $kind));
                $channels[] = 'email';
            } catch (Throwable $e) {
                Log::warning('reminder.email.failed', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);
            }
        }

        if ($owner->phone !== null && $owner->phone_verified_at !== null) {
            $result = $notifier->whatsapp($owner->phone, __('platform.reminders.whatsapp', ['name' => $owner->name, 'url' => $resume], $locale));
            if ($result->accepted) {
                $channels[] = 'whatsapp';
            } else {
                Log::warning('reminder.whatsapp.failed', ['tenant_id' => $tenant->id, 'error' => $result->error]);
            }
        }

        return $channels;
    }
}
