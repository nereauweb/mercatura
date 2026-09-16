<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\HomeSlides\Pages;

use App\Filament\Resources\Content\HomeSlides\HomeSlideResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageHomeSlides extends ManageRecords
{
    protected static string $resource = HomeSlideResource::class;

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);
        HomeSlideResource::flush();
    }
}
