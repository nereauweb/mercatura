<?php

declare(strict_types=1);

namespace App\Support\Reports;

/**
 * The technical part of a forwarded report as plain text (docs/06_USER_REPORTS.md §6),
 * for targets that show the description but no structured context.
 */
final class TechnicalBlock
{
    /** @param  array<string, mixed>  $context  reference, area, route, viewport, js_errors */
    public static function render(array $context): string
    {
        $lines = [__('admin.message.forward.tech.reference', ['reference' => (string) ($context['reference'] ?? '-')])];
        $area = isset($context['area']) ? __('admin.message.areas.'.$context['area']) : '-';
        $lines[] = __('admin.message.forward.tech.area', ['area' => $area, 'route' => (string) ($context['route'] ?? '-')]);
        if (filled($context['viewport'] ?? null)) {
            $lines[] = __('admin.message.forward.tech.viewport', ['viewport' => (string) $context['viewport']]);
        }
        $errors = array_values(array_filter((array) ($context['js_errors'] ?? []), 'is_string'));
        if ($errors !== []) {
            $lines[] = __('admin.message.forward.tech.js_errors');
            foreach ($errors as $error) {
                $lines[] = '- '.$error;
            }
        }

        return implode("\n", $lines);
    }

    /** The page without its query string: the path is enough to find it, the query may carry personal input. */
    public static function pageWithoutQuery(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return strtok($url, '?#') ?: null;
    }
}
