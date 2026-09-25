<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Host-level 301/302 (renames, alias hosts, base-domain change). Spec §8 — 006.
 */
#[Fillable(['from_host', 'to_host', 'status_code', 'expires_at'])]
class RedirectRule extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /** @param  Builder<RedirectRule>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
