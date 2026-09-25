<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A one-time sign-in code (spec §8 — 003), hashed at rest; see App\Auth\OtpService.
 *
 * @property string $identifier
 * @property string $channel
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property int $attempts
 * @property Carbon|null $consumed_at
 * @property string|null $ip
 */
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
