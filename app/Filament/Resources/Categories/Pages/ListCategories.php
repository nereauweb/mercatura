<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Support\CatalogCache;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);
        CatalogCache::flush();
    }
}
