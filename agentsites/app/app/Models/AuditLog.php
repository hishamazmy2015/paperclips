<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Append-only record of mutating actions. Spec §8 — 018. Written through App\Platform\Audit. */
#[Fillable(['actor_type', 'actor_id', 'account_id', 'tenant_id', 'action', 'target_type', 'target_id', 'changes', 'ip', 'user_agent', 'request_id', 'created_at'])]
class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_log';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
