<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Pages;

use App\Filament\Resources\Quotations\QuotationResource;
use Filament\Resources\Pages\ListRecords;

final class ListQuotations extends ListRecords
{
    protected static string $resource = QuotationResource::class;
}
