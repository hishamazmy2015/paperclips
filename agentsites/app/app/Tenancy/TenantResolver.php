<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Domain;
use App\Platform\Hosts;

/**
 * Host → resolution (spec §11 steps 1–4). Pure lookup: the middleware turns the result into
 * a response. With warm caches this makes no database query.
 */
final class TenantResolver
{
    public function __construct(
        private readonly HostCache $hosts,
        private readonly RedirectRules $redirects,
    ) {}

    public function resolve(string $rawHost): Resolution
    {
        $host = HostName::normalize($rawHost);

        if ($host === '' || Hosts::isPlatformHost($host) || Hosts::isLegacyHost($host)) {
            return Resolution::platform();
        }

        $redirect = $this->redirects->lookup($host);
        if ($redirect !== null) {
            return Resolution::redirect($redirect['to_host'], $redirect['status_code']);
        }

        $entry = $this->hosts->lookup($host);
        if ($entry === null) {
            return Resolution::unknown();
        }

        if ($entry['role'] === Domain::ROLE_ALIAS && $entry['primary_host'] !== $host) {
            return Resolution::redirect($entry['primary_host']);
        }

        return Resolution::tenant($entry);
    }
}
