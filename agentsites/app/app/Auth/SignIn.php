<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\Audit;
use App\Platform\EventLog;
use App\Provisioning\PhoneNormalizer;
use App\Provisioning\ProvisionInput;
use App\Provisioning\ProvisionTenant;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a verified identifier (email code / magic link, WhatsApp OTP, Google) into a signed-in
 * user. First sign-in creates the account (trial, 14 days), its owner and the draft site the
 * wizard autosaves into (spec §13 S1). No passwords anywhere (spec §6).
 */
final class SignIn
{
    public function __construct(
        private readonly ProvisionTenant $provision,
        private readonly PhoneNormalizer $phones,
        private readonly EventLog $events,
        private readonly Audit $audit,
        private readonly AuthFactory $auth,
    ) {}

    public function withEmail(string $email, string $locale, ?string $name = null, string $provider = 'email', ?string $googleId = null): User
    {
        $email = strtolower(trim($email));

        return $this->resolve(
            static function () use ($email, $googleId): ?User {
                $query = User::query()->where('email', $email);
                if ($googleId !== null) {
                    $query->orWhere('google_id', $googleId);
                }

                return $query->orderBy('id')->first();
            },
            ['email' => $email, 'name' => $name, 'auth_provider' => $provider, 'google_id' => $googleId, 'email_verified_at' => now()],
            $locale,
        );
    }

    public function withPhone(string $phone, string $locale): User
    {
        return $this->resolve(
            static fn (): ?User => User::query()->where('phone', $phone)->orderBy('id')->first(),
            ['phone' => $phone, 'auth_provider' => 'whatsapp', 'phone_verified_at' => now()],
            $locale,
        );
    }

    /**
     * The draft site every new agent gets at S1 so autosave has a target (spec §13). Returns
     * the account's existing site when there is one (a returning agent resumes it).
     */
    public function draft(User $user, string $locale): Tenant
    {
        $existing = Tenant::query()->where('account_id', $user->account_id)->orderBy('id')->first();
        if ($existing !== null) {
            return $existing;
        }

        $tenant = $this->provision->handle(new ProvisionInput(
            accountId: (int) $user->account_id,
            name: $user->name,
            whatsapp: $this->phones->normalize((string) ($user->phone ?? '')) ?? '',
            locale: $locale,
            email: $user->email,
            partial: true,
        ));
        $tenant->onboarding_step = 1;
        $tenant->save();

        return $tenant;
    }

    /** A readable placeholder for the site until the agent types their name at S2. */
    public static function placeholderName(?string $name, ?string $email): string
    {
        $name = trim((string) $name);
        if (mb_strlen($name) >= 2) {
            return mb_substr($name, 0, 80);
        }
        $local = trim((string) strstr((string) $email, '@', true));
        $local = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $local) ?? '');
        if (mb_strlen($local) >= 2) {
            return Str::title(mb_substr($local, 0, 80));
        }

        return 'Agent';
    }

    /**
     * @param  callable(): ?User  $find
     * @param  array<string, mixed>  $attributes
     */
    private function resolve(callable $find, array $attributes, string $locale): User
    {
        $user = $find();
        $created = $user === null;
        if ($user === null) {
            $user = DB::transaction(fn (): User => $this->create($attributes, $locale));
        } else {
            $this->complete($user, $attributes);
        }

        $user->last_login_at = now();
        $user->save();

        $this->auth->guard()->login($user, true);
        if (app()->bound('session.store')) {
            session()->regenerate();
        }

        $this->audit->record($created ? 'user.signed_up' : 'user.signed_in', ['provider' => (string) $attributes['auth_provider']], account: $user->account, actorType: Audit::ACTOR_USER, actorId: $user->id, targetType: User::class, targetId: $user->id);
        if ($created) {
            $this->events->record('account.created', ['provider' => (string) $attributes['auth_provider']], account: $user->account, user: $user);
        }

        return $user;
    }

    /** @param  array<string, mixed>  $attributes */
    private function create(array $attributes, string $locale): User
    {
        $account = Account::query()->create([
            'type' => Account::TYPE_AGENT,
            'plan_key' => (string) config('plans.default'),
            'plan_status' => 'trialing',
            'trial_ends_at' => now()->addDays((int) config('plans.trial_days')),
            'locale' => $locale,
            'timezone' => 'Asia/Dubai',
        ]);
        $user = $account->users()->create([
            'name' => self::placeholderName($attributes['name'] ?? null, $attributes['email'] ?? null),
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'role' => User::ROLE_OWNER,
            'auth_provider' => (string) $attributes['auth_provider'],
            'google_id' => $attributes['google_id'] ?? null,
            'email_verified_at' => $attributes['email_verified_at'] ?? null,
            'phone_verified_at' => $attributes['phone_verified_at'] ?? null,
        ]);
        $account->owner_user_id = $user->id;
        $account->save();

        $this->draft($user, $locale);

        return $user;
    }

    /** A returning user picks up whatever the new sign-in proved (a verified email, a Google id). */
    /** @param  array<string, mixed>  $attributes */
    private function complete(User $user, array $attributes): void
    {
        foreach (['email', 'phone', 'google_id', 'email_verified_at', 'phone_verified_at'] as $key) {
            if (($attributes[$key] ?? null) !== null && $user->getAttribute($key) === null) {
                $user->setAttribute($key, $attributes[$key]);
            }
        }
        if (($attributes['name'] ?? null) !== null && trim((string) $user->name) === '') {
            $user->name = (string) $attributes['name'];
        }
    }
}
