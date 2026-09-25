<?php

declare(strict_types=1);

namespace App\Platform;

use App\Models\Account;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Funnel and product events (spec §8 — 017, §13). Append-only; failures never break the
 * caller (an analytics row is not worth a failed publish).
 */
final class EventLog
{
    /** @param  array<string, mixed>  $properties */
    public function record(string $name, array $properties = [], ?Tenant $tenant = null, ?Account $account = null, ?User $user = null, ?Request $request = null): void
    {
        $request ??= app()->bound('request') && app()->runningInConsole() === false ? request() : null;

        Event::query()->create([
            'name' => $name,
            'tenant_id' => $tenant?->id,
            'account_id' => $account->id ?? $tenant?->account_id,
            'user_id' => $user?->id,
            'session_id' => $request?->hasSession() ? substr($request->session()->getId(), 0, 64) : null,
            'properties' => $properties === [] ? null : $properties,
            'device' => $request !== null ? self::device($request) : null,
            'locale' => app()->getLocale(),
            'ip' => $request?->ip(),
            'created_at' => now(),
        ]);
    }

    private static function device(Request $request): string
    {
        $ua = strtolower((string) $request->userAgent());

        return preg_match('/mobile|iphone|android|ipad/', $ua) === 1 ? 'mobile' : 'desktop';
    }
}
