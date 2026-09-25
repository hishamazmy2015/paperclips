<?php

declare(strict_types=1);

namespace App\Auth\Exceptions;

use RuntimeException;

/** Too many codes sent or checked for this identifier/IP (spec §17). */
final class OtpThrottled extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many attempts; retry in '.$retryAfterSeconds.' s');
    }
}
