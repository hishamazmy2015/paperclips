<?php

declare(strict_types=1);

namespace App\Provisioning;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The account a CLI/CSV-created site belongs to: an explicit account id, else the agent's
 * existing account (matched by owner phone or email), else a new trial agent account with its
 * owner user. Keeps re-runs idempotent (spec §12, §15).
 */
final class AccountResolver
{
    public function __construct(private readonly PhoneNormalizer $phones) {}

    public function resolve(ProvisionInput $input, ?int $accountId = null): Account
    {
        if ($accountId !== null) {
            return Account::query()->findOrFail($accountId);
        }

        $phone = $this->phones->normalize($input->whatsapp);
        $email = $input->email !== null ? strtolower($input->email) : null;

        if ($phone !== null || $email !== null) {
            $existing = User::query()
                ->where(function ($q) use ($phone, $email): void {
                    if ($phone !== null) {
                        $q->orWhere('phone', $phone);
                    }
                    if ($email !== null) {
                        $q->orWhere('email', $email);
                    }
                })
                ->first();
            if ($existing !== null) {
                return $existing->account;
            }
        }

        return DB::transaction(function () use ($input, $phone, $email): Account {
            $account = Account::query()->create([
                'type' => Account::TYPE_AGENT,
                'plan_key' => (string) config('plans.default'),
                'plan_status' => 'trialing',
                'trial_ends_at' => now()->addDays((int) config('plans.trial_days')),
                'locale' => $input->locale ?? (string) config('platform.default_locale'),
            ]);
            $user = $account->users()->create([
                'name' => $input->name,
                'email' => $email,
                'phone' => $phone,
                'role' => User::ROLE_OWNER,
                'auth_provider' => 'cli',
            ]);
            $account->owner_user_id = $user->id;
            $account->save();

            return $account;
        });
    }
}
