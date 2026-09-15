<?php

declare(strict_types=1);

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\BrandResource;

final class CreateBrand extends \Filament\Resources\Pages\CreateRecord
{
    protected static string $resource = BrandResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return BrandData::normalise($data);
    }

    protected function afterCreate(): void
    {
        BrandData::syncProducts($this->getRecord());
    }
}
