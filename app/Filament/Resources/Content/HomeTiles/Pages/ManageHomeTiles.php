<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\HomeTiles\Pages;

use App\Filament\Resources\Content\HomeTiles\HomeTileResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageHomeTiles extends ManageRecords
{
    protected static string $resource = HomeTileResource::class;

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);
        HomeTileResource::flush();
    }
}
