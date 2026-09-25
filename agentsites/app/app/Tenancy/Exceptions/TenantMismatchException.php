<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use RuntimeException;

/** An attempt to write a row for a tenant other than the bound one (spec §17, §22.4). */
final class TenantMismatchException extends RuntimeException
{
    public function __construct(int $given, int $bound)
    {
        parent::__construct(sprintf('Refusing to write tenant_id %d while tenant %d is bound.', $given, $bound));
    }
}
