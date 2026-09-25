<?php

declare(strict_types=1);

namespace App\Platform;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Tenant;

/** Every mutating action leaves a row in audit_log (spec §8 — 018, §17). */
final class Audit
{
    public const ACTOR_SYSTEM = 'system';

    public const ACTOR_USER = 'user';

    public const ACTOR_ADMIN = 'admin';

    public const ACTOR_AGENT_KEY = 'agent_key';

    /** @param  array<string, mixed>  $changes */
    public function record(
        string $action,
        array $changes = [],
        ?Tenant $tenant = null,
        ?Account $account = null,
        string $actorType = self::ACTOR_SYSTEM,
        ?int $actorId = null,
        ?string $targetType = null,
        ?int $targetId = null,
    ): AuditLog {
        $request = app()->bound('request') && ! app()->runningInConsole() ? request() : null;

        return AuditLog::query()->create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'account_id' => $account->id ?? $tenant?->account_id,
            'tenant_id' => $tenant?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'changes' => $changes === [] ? null : $changes,
            'ip' => $request?->ip(),
            'user_agent' => $request !== null ? substr((string) $request->userAgent(), 0, 300) : null,
            'request_id' => $request?->header('X-Request-Id'),
            'created_at' => now(),
        ]);
    }
}
