<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\HomeSlides\Pages;

use App\Filament\Resources\Content\HomeSlides\HomeSlideResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageHomeSlides extends ManageRecords
{
    protected static string $resource = HomeSlideResource::class;
}
