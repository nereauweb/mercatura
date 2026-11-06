<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Brand;
use Illuminate\Support\Str;

/**
 * Links an imported brand label to a brand record (Catalogo → Marchi).
 *
 * Match by name, then by the slug the label would get, so a brand renamed in the panel
 * (slug kept) still catches the supplier's label. Unknown labels create an active brand.
 * A brand deleted in the panel is not recreated: its products stay unlinked.
 * Empty, "0" and "Unbranded" mean no brand. One instance per import run (memoised).
 */
final class BrandResolver
{
    private const NO_BRAND = ['', '0', 'Unbranded'];

    /** @var array<string, Brand|null> */
    private array $resolved = [];

    public function resolve(mixed $label): ?Brand
    {
        $label = is_string($label) ? trim($label) : '';
        if (in_array($label, self::NO_BRAND, true)) {
            return null;
        }
        if (array_key_exists($label, $this->resolved)) {
            return $this->resolved[$label];
        }
        $slug = Str::slug($label) ?: 'brand-'.substr(md5($label), 0, 8);
        $brand = Brand::withTrashed()->where('name', $label)->first()
            ?? Brand::withTrashed()->where('slug', $slug)->first();
        if ($brand === null) {
            $brand = Brand::query()->create([
                'name' => mb_substr($label, 0, 64),
                'slug' => $slug,
                'active' => true,
                'position' => (int) Brand::withTrashed()->max('position') + 1,
            ]);
        }

        return $this->resolved[$label] = $brand->trashed() ? null : $brand;
    }
}
