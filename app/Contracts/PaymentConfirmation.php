<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Outcome of PaymentGateway::confirm(). Anything but Confirmed renders the
 * payment verification page with the matching lang key.
 */
enum PaymentConfirmation: string
{
    case Confirmed = 'confirmed';
    case MissingReference = 'missing_reference';
    case NotPending = 'not_pending';
    case Unverifiable = 'unverifiable';
    case Mismatch = 'mismatch';

    public function messageKey(): string
    {
        return 'frontend.checkout.verification_'.$this->value;
    }
}
