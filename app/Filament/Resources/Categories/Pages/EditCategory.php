<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Actions\Catalog\DeleteCategory;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Models\Category;
use App\Support\CatalogCache;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label(__('admin.common.storefront'))->icon('heroicon-o-eye')->color('gray')
                ->url(fn (Category $record): string => route('frontend.category.show.by_slug', ['slug' => $record->slug()]), shouldOpenInNewTab: true),
            DeleteAction::make()->using(fn (Category $record) => app(DeleteCategory::class)->handle($record)),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return CategoryIconPaths::toUploads($data, CategoryForm::ICON_DIR);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CategoryIconPaths::toColumns($data, CategoryForm::ICON_DIR);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record instanceof Category) {
            $record->slug();
        }
        CatalogCache::flush();
    }
}
