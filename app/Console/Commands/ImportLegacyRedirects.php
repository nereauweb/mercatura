<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LegacyRedirect;
use Illuminate\Console\Command;

/**
 * Loads a redirect map into legacy_redirects (docs/ARCHITECTURE.md §12): a
 * CSV with two or three columns, old path, new path, optional status code
 * (301 default). Blank lines and lines starting with # are skipped; a header
 * row named from/to is skipped. The map itself belongs to the installation.
 */
final class ImportLegacyRedirects extends Command
{
    protected $signature = 'mercatura:redirects-import {file : CSV file: from_path,to_path[,status_code]} {--status=301 : Status code for rows without one} {--delimiter=, : Field delimiter}';

    protected $description = 'Load a CSV redirect map into legacy_redirects (no chains, existing rows updated)';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_readable($file)) {
            $this->error("Cannot read {$file}");

            return self::FAILURE;
        }
        $default = (int) $this->option('status');
        $delimiter = (string) $this->option('delimiter') ?: ',';
        $handle = fopen($file, 'r');
        if ($handle === false) {
            $this->error("Cannot open {$file}");

            return self::FAILURE;
        }

        $written = $skipped = 0;
        $line = 0;
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line++;
            $from = trim((string) ($row[0] ?? ''));
            $to = trim((string) ($row[1] ?? ''));
            if ($from === '' || str_starts_with($from, '#') || ($line === 1 && in_array(strtolower($from), ['from', 'from_path', 'old', 'source'], true))) {
                continue;
            }
            if ($to === '') {
                $this->warn("Line {$line}: no target for {$from}, skipped");
                $skipped++;

                continue;
            }
            $status = (int) (trim((string) ($row[2] ?? '')) ?: $default);
            if (! in_array($status, [301, 302, 307, 308, 410], true)) {
                $this->warn("Line {$line}: status {$status} not allowed, skipped");
                $skipped++;

                continue;
            }
            LegacyRedirect::record($from, $to, $status);
            $written++;
        }
        fclose($handle);

        $this->info("Redirects written: {$written}, skipped: {$skipped}");

        return self::SUCCESS;
    }
}
