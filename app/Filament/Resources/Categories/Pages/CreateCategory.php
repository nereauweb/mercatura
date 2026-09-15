<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Models\Category;
use App\Support\CatalogCache;
use Filament\Resources\Pages\CreateRecord;

final class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return CategoryIconPaths::toColumns($data, CategoryForm::ICON_DIR);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        if ($record instanceof Category) {
            $record->slug(); // generated from the name when left empty
        }
        CatalogCache::flush();
    }
}
