<?php

declare(strict_types=1);

namespace App\Services\GreenApi;

use RuntimeException;

/**
 * A Green API call failed. retryable = worth trying again later (network, 429, 5xx);
 * otherwise (bad number, bad token, 4xx) retrying won't help.
 */
class GreenApiException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
