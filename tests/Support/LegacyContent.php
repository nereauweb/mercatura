<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * The storefront must carry no trace of the installation the core was
 * bootstrapped from. The public part of the pattern is generic (legacy
 * colour classes, tag manager ids); the identifying tokens live in the
 * git-ignored file tests/legacy-content.patterns, one regular expression
 * per line, and are only checked where that file exists.
 */
final class LegacyContent
{
    public const PATTERNS_FILE = __DIR__.'/../legacy-content.patterns';

    /** A case-insensitive regex of the private tokens plus the given public ones. */
    public static function pattern(string $public = ''): string
    {
        $parts = array_values(array_filter([...self::privateTokens(), $public]));

        return '/'.($parts === [] ? '(?!)' : implode('|', $parts)).'/i';
    }

    /** @return list<string> */
    private static function privateTokens(): array
    {
        if (! is_file(self::PATTERNS_FILE)) {
            return [];
        }
        $lines = file(self::PATTERNS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#')));
    }
}
