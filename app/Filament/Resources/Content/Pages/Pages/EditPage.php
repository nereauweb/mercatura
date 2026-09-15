<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\Pages\Pages;

use App\Filament\Resources\Content\Pages\PageResource;
use App\Models\Page;
use App\Support\CatalogCache;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label(__('admin.common.storefront'))->icon('heroicon-o-eye')->color('gray')
                ->url(fn (Page $record): string => route('frontend.contents.page', ['slug' => $record->slug]), shouldOpenInNewTab: true),
            DeleteAction::make()->after(fn () => CatalogCache::flush()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Page $page */
        $page = $this->getRecord();

        return PageContentsData::fill($page, $data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        [$data, $filters] = PageContentsData::split($data);
        $record->fill($data)->save();
        PageContentsData::save($record, $filters);

        return $record;
    }
}
