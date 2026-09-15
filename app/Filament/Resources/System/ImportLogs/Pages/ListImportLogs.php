<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\ImportLogs\Pages;

use App\Filament\Resources\System\ImportLogs\ImportLogResource;
use Filament\Resources\Pages\ListRecords;

final class ListImportLogs extends ListRecords
{
    protected static string $resource = ImportLogResource::class;
}
