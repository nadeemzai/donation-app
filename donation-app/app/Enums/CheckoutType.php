<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the website / app must do after a payment is started.
 */
enum CheckoutType: string
{
    case Redirect = 'redirect';            // send the donor to a gateway URL
    case FormPost = 'form_post';           // auto-submit a form to the gateway (JazzCash hosted page)
    case AwaitingDonor = 'awaiting_donor'; // donor approves on their phone (wallet push); poll status
}
