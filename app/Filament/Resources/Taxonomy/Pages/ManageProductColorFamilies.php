<?php

declare(strict_types=1);

namespace App\Filament\Resources\Taxonomy\Pages;

use App\Filament\Resources\Taxonomy\ProductColorFamilyResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageProductColorFamilies extends ManageRecords
{
    protected static string $resource = ProductColorFamilyResource::class;
}
