<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\LegacyRedirects\Pages;

use App\Filament\Resources\Content\LegacyRedirects\LegacyRedirectResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageLegacyRedirects extends ManageRecords
{
    protected static string $resource = LegacyRedirectResource::class;
}
