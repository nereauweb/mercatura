<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductColor;
use App\Support\CatalogCache;
use Illuminate\Console\Command;

/**
 * Fills the hex code of the colours the suppliers sent without one, from
 * config('mercatura.catalog.color_codes'): a plain label maps directly, a
 * composite label ("blu/bianco", "nero-rosso") is resolved part by part and
 * stored as "HEX1/HEX2". Codes already set are never touched.
 */
class CatalogColorCodes extends Command
{
    protected $signature = 'catalog:color-codes {--dry-run : Report without writing}';

    protected $description = 'Fill the hex code of the colours that have none, from the configured colour map';

    public function handle(): int
    {
        $map = [];
        foreach ((array) config('mercatura.catalog.color_codes', []) as $label => $hex) {
            $map[mb_strtolower(trim((string) $label))] = (string) $hex;
        }
        $filled = 0;
        $unresolved = [];
        $colors = ProductColor::query()->where(fn ($q) => $q->whereNull('code')->orWhere('code', ''))->orderBy('label')->get();
        foreach ($colors as $color) {
            $code = self::resolve((string) $color->label, $map);
            if ($code === null) {
                $unresolved[] = (string) $color->label;

                continue;
            }
            $this->line($color->label.' => '.$code);
            if (! $this->option('dry-run')) {
                $color->forceFill(['code' => $code])->save();
            }
            $filled++;
        }
        if ($filled > 0 && ! $this->option('dry-run')) {
            CatalogCache::flush();
        }
        $this->info(sprintf('Colour codes: %d filled, %d without a match%s', $filled, count($unresolved), $unresolved === [] ? '' : ' ('.implode(', ', $unresolved).')'));

        return self::SUCCESS;
    }

    /**
     * The code for a label, or null when a part of it is not in the map.
     *
     * @param  array<string, string>  $map  lower-case label => hex
     */
    public static function resolve(string $label, array $map): ?string
    {
        $key = mb_strtolower(trim($label));
        // "blu-notte" is the single colour "blu notte" when the map knows it, not blu + notte.
        foreach ([$key, preg_replace('/\s*-\s*/u', ' ', $key)] as $whole) {
            if (isset($map[$whole])) {
                return $map[$whole];
            }
        }
        $parts = preg_split('/\s*[\/\-]\s*/u', $key) ?: [];
        if (count($parts) < 2) {
            return null;
        }
        $codes = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || ! isset($map[$part])) {
                return null;
            }
            $codes[] = $map[$part];
        }

        return implode('/', $codes);
    }
}
