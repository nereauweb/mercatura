<?php

declare(strict_types=1);

namespace App\Support\Reports;

/**
 * Masks personal data in free text before it leaves the platform
 * (docs/06_USER_REPORTS.md §2.5, §6): email addresses, IBANs, card-like
 * numbers and phone numbers. Names and addresses cannot be recognised
 * reliably: the admin reviews the text before forwarding.
 */
final class Redactor
{
    private const PATTERNS = [
        '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
        '/\b[A-Z]{2}\d{2}(?: ?[A-Z0-9]{4}){2,7}(?: ?[A-Z0-9]{1,4})?\b/',
        '/\b(?:\d[ -]?){13,19}\b/',
        // 9 to 15 digits with optional separators; not inside words, paths, ids (#123) or decimals.
        '/(?<![\w\/.#-])\+?\d(?:[\s.\/-]?\d){8,14}(?![\w\/-]|\.\d)/',
    ];

    public static function text(string $text): string
    {
        return (string) preg_replace(self::PATTERNS, __('admin.message.forward.redacted'), $text);
    }
}
