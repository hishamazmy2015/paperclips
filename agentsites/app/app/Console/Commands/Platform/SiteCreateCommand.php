<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Provisioning\Exceptions\InvalidProvisionInput;
use App\Provisioning\Exceptions\SlugUnavailable;
use App\Provisioning\PhoneNormalizer;
use App\Provisioning\ProvisionInput;
use App\Provisioning\ProvisionTenant;
use Illuminate\Support\Facades\DB;

/**
 * platform:site:create — spec §15 / acceptance test A1:
 *   platform:site:create --name "Ahmed Al Falasi" --whatsapp +9715xxxxxxx
 * → a live site on {slug}.{base}, no restart. Idempotent on (account, slug).
 */
final class SiteCreateCommand extends PlatformCommand
{
    protected $signature = 'platform:site:create
        {--config= : JSON file in the samples/agent.json shape (or "-" for stdin)}
        {--name= : Agent display name}
        {--whatsapp= : WhatsApp number (E.164; UAE numbers may be local)}
        {--slug= : Wanted subdomain label (derived from the name when omitted)}
        {--theme= : Theme key (default: config themes.default)}
        {--locale= : Default locale ar|en}
        {--agency= : Agency name}
        {--license= : License / BRN}
        {--email= : Owner email (creates the account user with it)}
        {--areas= : Comma-separated service areas}
        {--account= : Existing account id (a new agent account is created when omitted)}
        {--draft : Keep the site as a draft instead of publishing it}';

    protected $description = 'Create a site (data only: no deploy, no restart) and publish it unless --draft';

    public function handle(ProvisionTenant $provision, PhoneNormalizer $phones): int
    {
        $data = $this->inputData();
        if ($data === null) {
            return self::FAILURE;
        }

        $publish = ! (bool) $this->option('draft');
        $probe = ProvisionInput::fromArray($data, 0, $publish);
        if ($probe->name === '' || $probe->whatsapp === '') {
            return $this->failWith('--name and --whatsapp are required (or a --config file that carries identity.display_name and contact.whatsapp)');
        }

        $before = Tenant::query()->count();

        try {
            $account = $this->account($probe, $phones);
            $tenant = $provision->handle(ProvisionInput::fromArray($data, $account->id, $publish));
        } catch (SlugUnavailable $e) {
            return $this->failWith($e->getMessage(), ['slug' => $e->slug, 'reason' => $e->reason, 'suggestions' => $e->suggestions]);
        } catch (InvalidProvisionInput $e) {
            return $this->failWith('invalid input', ['errors' => $e->errors]);
        }

        $created = Tenant::query()->count() > $before;

        return $this->emit(
            $this->describe($tenant, $created),
            sprintf('%s %s (%s) → %s', $created ? 'Created' : 'Already exists:', $tenant->slug, $tenant->status, $tenant->url()),
        );
    }

    /** @return array<string, mixed>|null */
    private function inputData(): ?array
    {
        $data = [];
        $path = (string) $this->option('config');
        if ($path !== '') {
            $raw = $path === '-' ? (string) stream_get_contents(STDIN) : (string) @file_get_contents($path);
            if ($raw === '') {
                $this->failWith("cannot read config file: {$path}");

                return null;
            }
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                $this->failWith("config file is not valid JSON: {$path}");

                return null;
            }
            $data = $decoded;
        }

        foreach (['name', 'whatsapp', 'slug', 'theme', 'locale', 'agency', 'license', 'email', 'areas'] as $option) {
            $value = $this->option($option);
            if (is_string($value) && $value !== '') {
                $data[$option] = $value;
            }
        }

        return $data;
    }

    /** The account to own the site: --account, else the agent's existing account (by phone or email), else a new trial account. */
    private function account(ProvisionInput $input, PhoneNormalizer $phones): Account
    {
        $accountId = $this->option('account');
        if (is_string($accountId) && $accountId !== '') {
            return Account::query()->findOrFail((int) $accountId);
        }

        $phone = $phones->normalize($input->whatsapp);
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
