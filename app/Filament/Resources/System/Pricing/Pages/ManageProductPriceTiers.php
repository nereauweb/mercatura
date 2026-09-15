<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\Pricing\Pages;

use App\Filament\Resources\System\Pricing\ProductPriceTierResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageProductPriceTiers extends ManageRecords
{
    protected static string $resource = ProductPriceTierResource::class;

    public function getSubheading(): string
    {
        return __('admin.pricing.tiers_hint');
    }
}
