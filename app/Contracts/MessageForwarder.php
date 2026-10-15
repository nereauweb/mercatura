<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Reports\ForwardedReport;
use RuntimeException;

/**
 * Sends a report on to an external helpdesk (docs/06_USER_REPORTS.md §6).
 * The core ships only the null driver; an installation package binds a real
 * one under `mercatura.message-forwarders.<name>` and selects it with
 * MERCATURA_MESSAGE_FORWARDER=<name>. The panel shows the forward and the
 * admin's own report only when isAvailable() is true.
 */
interface MessageForwarder
{
    /** Configured and able to send now (credentials present, environment allowed). */
    public function isAvailable(): bool;

    /** Name of the target shown in the panel (e.g. the helpdesk's name). */
    public function label(): string;

    /**
     * Hands the report to the target; returns its reference (ticket id, or a
     * queue reference while delivery is pending).
     *
     * @throws RuntimeException when the report could not be taken over
     */
    public function forward(ForwardedReport $report): string;
}
