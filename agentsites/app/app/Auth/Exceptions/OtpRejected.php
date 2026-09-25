<?php

declare(strict_types=1);

namespace App\Auth\Exceptions;

use RuntimeException;

/** A code that does not sign anyone in: expired (or never issued), wrong, or out of attempts. */
final class OtpRejected extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?int $attemptsLeft = null)
    {
        parent::__construct('Code rejected: '.$reason);
    }
}
