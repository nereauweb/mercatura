<?php

declare(strict_types=1);

namespace App\Filament\Resources\Taxonomy\Pages;

use App\Filament\Resources\Taxonomy\ProductSizeResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageProductSizes extends ManageRecords
{
    protected static string $resource = ProductSizeResource::class;
}
