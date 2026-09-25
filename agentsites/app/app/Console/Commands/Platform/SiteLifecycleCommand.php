<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Provisioning\Exceptions\CannotPublish;
use App\Provisioning\Exceptions\SlugUnavailable;

/** Shared body for platform:site:{publish,suspend,restore,delete,rename}. Spec §15. */
abstract class SiteLifecycleCommand extends PlatformCommand
{
    protected function withTenant(string $slug, callable $fn, bool $withTrashed = false): int
    {
        $tenant = $this->findTenant($slug, $withTrashed);
        if ($tenant === null) {
            return $this->failWith("no tenant with slug '{$slug}'");
        }

        try {
            return $fn($tenant);
        } catch (SlugUnavailable $e) {
            return $this->failWith($e->getMessage(), ['slug' => $e->slug, 'reason' => $e->reason, 'suggestions' => $e->suggestions]);
        } catch (CannotPublish $e) {
            return $this->failWith($e->getMessage(), ['missing' => $e->missing]);
        }
    }
}
