<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use RuntimeException;

/** A tenant-scoped query ran with no tenant bound and no explicit global access (spec §8). */
final class MissingTenantContextException extends RuntimeException
{
    public function __construct(string $model)
    {
        parent::__construct(sprintf(
            'Tenant-scoped query on %s without an active TenantContext. Bind a tenant (TenantContext::with) or, for platform code, wrap the block in TenantContext::global.',
            $model,
        ));
    }
}
