<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\CategoryImportAliases\Pages;

use App\Filament\Resources\System\CategoryImportAliases\CategoryImportAliasResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageCategoryImportAliases extends ManageRecords
{
    protected static string $resource = CategoryImportAliasResource::class;
}
