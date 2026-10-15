<?php

declare(strict_types=1);

namespace App\Drivers\Messages;

use App\Contracts\MessageForwarder;
use App\Support\Reports\ForwardedReport;
use RuntimeException;

/** No forward target: the panel hides the forward and the admin's own report. */
final class NullMessageForwarder implements MessageForwarder
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function label(): string
    {
        return '';
    }

    public function forward(ForwardedReport $report): string
    {
        throw new RuntimeException('No message forwarder is configured.');
    }
}
