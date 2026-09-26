<?php

declare(strict_types=1);

namespace App\Caching;

use Illuminate\Database\Eloquent\Model;

/**
 * For tenant-scoped models whose rows appear on the site (listings, testimonials, domains):
 * any write purges the tenant's page cache (spec §16). Put it on the model with `use`.
 */
trait PurgesSitePages
{
    public static function bootPurgesSitePages(): void
    {
        $purge = static function (Model $model): void {
            $tenantId = (int) $model->getAttribute('tenant_id');
            if ($tenantId > 0) {
                app(PageCache::class)->purgeTenant($tenantId);
            }
        };
        static::saved($purge);
        static::deleted($purge);
        static::restored($purge); // every site-facing model soft-deletes
    }
}
