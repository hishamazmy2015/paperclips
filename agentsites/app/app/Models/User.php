<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A person who can sign in (Google, email code, WhatsApp OTP — no passwords). Spec §8 — 002.
 *
 * @property int $account_id
 * @property string|null $email
 * @property string|null $phone
 * @property string $name
 * @property string $role
 * @property string $auth_provider
 * @property string|null $google_id
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $reminders_opted_out_at
 */
#[Fillable(['account_id', 'email', 'phone', 'name', 'role', 'auth_provider', 'google_id', 'email_verified_at', 'phone_verified_at', 'last_login_at', 'reminders_opted_out_at'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_OWNER = 'owner';

    public const ROLE_MEMBER = 'member';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'reminders_opted_out_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }
}
