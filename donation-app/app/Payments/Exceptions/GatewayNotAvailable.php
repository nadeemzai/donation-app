<?php

declare(strict_types=1);

namespace App\Payments\Exceptions;

use DomainException;

/** The gateway is switched off, not configured, or has no adapter. Safe to show donors. */
class GatewayNotAvailable extends DomainException {}
