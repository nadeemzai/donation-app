<?php

declare(strict_types=1);

namespace App\Payments\Exceptions;

use DomainException;

/** e.g. the gateway has no refund API — refund manually and record it with DonationService. */
class GatewayOperationNotSupported extends DomainException {}
