<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Customizations\Customization;
use App\Support\CatalogCache;

/** One default customization per variant: the one the product page and the printed price table use. */
final class SetDefaultCustomization
{
    public function handle(Customization $customization): void
    {
        Customization::query()->where('variant_id', $customization->variant_id)->whereKeyNot($customization->getKey())->update(['is_default' => 0]);
        $customization->forceFill(['is_default' => 1])->save();
        CatalogCache::flush();
    }
}
