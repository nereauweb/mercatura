<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use Filament\Resources\Pages\ListRecords;

final class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;
}
