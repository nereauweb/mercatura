<?php

declare(strict_types=1);

namespace App\Filament\Resources\Taxonomy\Pages;

use App\Filament\Resources\Taxonomy\ProductColorResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageProductColors extends ManageRecords
{
    protected static string $resource = ProductColorResource::class;
}
