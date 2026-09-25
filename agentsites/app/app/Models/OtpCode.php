<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Spec §8 — 003 (auth flows in Phase 2). */
#[Fillable(['identifier', 'channel', 'code_hash', 'expires_at', 'attempts', 'consumed_at', 'ip'])]
class OtpCode extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
