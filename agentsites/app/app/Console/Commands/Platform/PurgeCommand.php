<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Media;
use App\Models\OtpCode;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Testimonial;
use App\Tenancy\RedirectRules;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * platform:purge — hard-delete what was soft-deleted more than 30 days ago (90 for billing
 * data), expired redirect rules and OTP codes (spec §8 invariants). Runs daily.
 */
final class PurgeCommand extends PlatformCommand
{
    public const DAYS = 30;

    public const BILLING_DAYS = 90;

    protected $signature = 'platform:purge {--dry-run : Count only}';

    protected $description = 'Hard-delete soft-deleted rows past their retention window';

    public function handle(RedirectRules $redirects): int
    {
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subDays(self::DAYS);
        $billingCutoff = now()->subDays(self::BILLING_DAYS);

        $counts = TenantContext::global(function () use ($dry, $cutoff, $billingCutoff): array {
            $counts = [];
            // tenants first: cascading foreign keys remove their children
            $counts['tenants'] = $this->purge(Tenant::onlyTrashed()->where('deleted_at', '<=', $cutoff), $dry);
            $counts['domains'] = $this->purge(Domain::unscopedByTenant()->onlyTrashed()->where('deleted_at', '<=', $cutoff), $dry);
            $counts['listings'] = $this->purge(Listing::unscopedByTenant()->onlyTrashed()->where('deleted_at', '<=', $cutoff), $dry);
            $counts['testimonials'] = $this->purge(Testimonial::unscopedByTenant()->onlyTrashed()->where('deleted_at', '<=', $cutoff), $dry);
            $counts['media'] = $this->purge(Media::unscopedByTenant()->onlyTrashed()->where('deleted_at', '<=', $cutoff), $dry);
            $counts['leads'] = $this->purge(Lead::unscopedByTenant()->onlyTrashed()->where('deleted_at', '<=', $cutoff), $dry);
            $counts['invoices'] = $this->purge(Invoice::onlyTrashed()->where('deleted_at', '<=', $billingCutoff), $dry);
            $counts['subscriptions'] = $this->purge(Subscription::onlyTrashed()->where('deleted_at', '<=', $billingCutoff), $dry);
            $counts['otp_codes'] = $dry ? OtpCode::query()->where('expires_at', '<=', now()->subDay())->count() : OtpCode::query()->where('expires_at', '<=', now()->subDay())->delete();

            return $counts;
        });
        $counts['redirect_rules'] = $dry ? 0 : $redirects->purgeExpired();

        return $this->emit(['ok' => true, 'dry_run' => $dry, 'purged' => $counts]);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function purge(Builder $query, bool $dry): int
    {
        if ($dry) {
            return $query->count();
        }
        $n = 0;
        foreach ($query->cursor() as $model) {
            $model->forceDelete();
            $n++;
        }

        return $n;
    }
}
