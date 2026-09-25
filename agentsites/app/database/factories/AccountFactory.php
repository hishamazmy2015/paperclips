<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'type' => Account::TYPE_AGENT,
            'plan_key' => 'trial',
            'plan_status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
            'locale' => 'en',
            'timezone' => 'Asia/Dubai',
        ];
    }

    public function brokerage(): static
    {
        return $this->state(fn (): array => ['type' => Account::TYPE_BROKERAGE]);
    }
}
