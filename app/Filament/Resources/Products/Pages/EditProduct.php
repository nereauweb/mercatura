<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Catalog\SyncProductCategories;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Brand;
use App\Models\Product;
use App\Support\CatalogCache;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label(__('admin.common.storefront'))->icon('heroicon-o-eye')->color('gray')
                ->url(fn (Product $record): string => route('frontend.product.show.by_slug', ['slug' => $record->slug()]), shouldOpenInNewTab: true),
            DeleteAction::make()->after(fn () => CatalogCache::flush()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Product $product */
        $product = $this->getRecord();
        $data['category_ids'] = $product->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();
        foreach (['isGreen', 'isPromo', 'isBestseller'] as $flag) {
            $data[$flag] = (string) (int) ($data[$flag] ?? 0);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Product $record */
        $categoryIds = array_map('intval', (array) ($data['category_ids'] ?? []));
        unset($data['category_ids']);
        $data['brand'] = isset($data['brand_id']) ? (string) Brand::query()->whereKey($data['brand_id'])->value('name') : null;
        // An unchanged or empty slug means "follow name and sku" (legacy behaviour); a new one is kept.
        if (trim((string) ($data['slug'] ?? '')) === '' || (string) $data['slug'] === (string) $record->slug) {
            unset($data['slug']);
        }

        $record->fill($data);
        $regenerateSlug = ! array_key_exists('slug', $data) && ($record->isDirty('name') || $record->isDirty('sku'));
        $record->save();
        if ($regenerateSlug) {
            $record->slug(true); // legacy: slug follows name and sku; the old one becomes a redirect
        }
        app(SyncProductCategories::class)->handle($record, $categoryIds);

        return $record;
    }

    protected function getSavedNotificationTitle(): string
    {
        return __('admin.catalog.updated');
    }
}
