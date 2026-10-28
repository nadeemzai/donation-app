<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A gateway's answer about one payment, after its signature/hash was verified.
 */
enum GatewayOutcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Pending = 'pending'; // gateway hasn't decided yet
}
