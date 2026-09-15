<?php

declare(strict_types=1);

namespace App\Filament\Resources\Taxonomy\Pages;

use App\Filament\Resources\Taxonomy\ProductSizeTypeResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageProductSizeTypes extends ManageRecords
{
    protected static string $resource = ProductSizeTypeResource::class;
}
