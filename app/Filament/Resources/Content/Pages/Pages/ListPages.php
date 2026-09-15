<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\Pages\Pages;

use App\Filament\Resources\Content\Pages\PageResource;
use App\Support\CatalogCache;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

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
