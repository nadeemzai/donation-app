<?php

declare(strict_types=1);

namespace App\Payments\Exceptions;

use RuntimeException;

/** The callback's hash/signature doesn't match — it did not come from the gateway. */
class InvalidGatewaySignature extends RuntimeException {}
