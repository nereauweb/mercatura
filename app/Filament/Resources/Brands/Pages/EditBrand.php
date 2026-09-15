<?php

declare(strict_types=1);

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\BrandResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! empty($data['logo']) && str_starts_with((string) $data['logo'], '/storage/')) {
            $data['logo'] = substr((string) $data['logo'], strlen('/storage/'));
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BrandData::normalise($data);
    }

    protected function afterSave(): void
    {
        BrandData::syncProducts($this->getRecord());
    }
}
