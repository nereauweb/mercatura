<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\Pricing\Pages;

use App\Filament\Resources\System\Pricing\ProductMarkupResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageProductMarkups extends ManageRecords
{
    protected static string $resource = ProductMarkupResource::class;

    public function getSubheading(): string
    {
        return __('admin.pricing.markups_hint');
    }
}
