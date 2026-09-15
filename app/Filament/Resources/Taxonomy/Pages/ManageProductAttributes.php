<?php

declare(strict_types=1);

namespace App\Filament\Resources\Taxonomy\Pages;

use App\Filament\Resources\Taxonomy\ProductAttributeResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageProductAttributes extends ManageRecords
{
    protected static string $resource = ProductAttributeResource::class;
}
