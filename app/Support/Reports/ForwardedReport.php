<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Enums\ReportKind;

/**
 * What leaves the platform when a report is forwarded (docs/06_USER_REPORTS.md §6):
 * the reporter is the admin who sends it, the user is only a reference.
 * `technical` is a plain-text block for targets that show no structured
 * context; `attachments` are local paths the forwarder may read during forward().
 */
final readonly class ForwardedReport
{
    /**
     * @param  array{name: string, email: string}  $reporter
     * @param  array<string, mixed>  $context  reference, reporter_role, area, route, viewport, user_agent, js_errors
     * @param  list<array{path: string, name: string, mime: string}>  $attachments
     */
    public function __construct(
        public ReportKind $kind,
        public string $title,
        public string $description,
        public ?string $pageUrl,
        public array $reporter,
        public array $context,
        public string $technical,
        public array $attachments,
    ) {}
}
