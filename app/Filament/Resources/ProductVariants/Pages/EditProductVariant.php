<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductVariants\Pages;

use App\Actions\Catalog\SetMainVariant;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\ProductVariants\ProductVariantResource;
use App\Models\ProductVariant;
use App\Support\CatalogCache;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class EditProductVariant extends EditRecord
{
    protected static string $resource = ProductVariantResource::class;

    public function getTitle(): string
    {
        /** @var ProductVariant $variant */
        $variant = $this->getRecord();

        return __('admin.catalog.variant').' '.$variant->sku;
    }

    /** The resource has no index: breadcrumbs and the post-save redirect lead back to the product. */
    public function getBreadcrumbs(): array
    {
        /** @var ProductVariant $variant */
        $variant = $this->getRecord();

        return [
            ProductResource::getUrl() => __('admin.catalog.products'),
            ProductResource::getUrl('edit', ['record' => $variant->product_id]) => (string) ($variant->product->name ?? $variant->product_id),
            __('admin.catalog.variant').' '.$variant->sku,
        ];
    }

    protected function getRedirectUrl(): string
    {
        /** @var ProductVariant $variant */
        $variant = $this->getRecord();

        return ProductResource::getUrl('edit', ['record' => $variant->product_id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('product')->label(__('admin.catalog.product'))->icon('heroicon-o-arrow-uturn-left')->color('gray')
                ->url(fn (ProductVariant $record): string => ProductResource::getUrl('edit', ['record' => $record->product_id])),
            DeleteAction::make()
                ->successRedirectUrl(fn (ProductVariant $record): string => ProductResource::getUrl('edit', ['record' => $record->product_id]))
                ->after(function (ProductVariant $record): void {
                    if ($record->product) {
                        app(SetMainVariant::class)->reelect($record->product);
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ProductVariant $variant */
        $variant = $this->getRecord();
        $data['isSale'] = (string) (int) ($data['isSale'] ?? 0);
        $data['attribute_values'] = DB::table('products_variants_attributes')->where('variant_id', $variant->id)->orderBy('attribute_id')
            ->get(['attribute_id', 'value'])->map(fn ($row) => ['attribute_id' => (int) $row->attribute_id, 'value' => (string) $row->value])->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ProductVariant $record */
        $attributeValues = (array) ($data['attribute_values'] ?? []);
        unset($data['attribute_values']);
        if (($data['next_stock_quantity'] ?? null) === null || $data['next_stock_quantity'] === '') {
            $data['next_stock_quantity'] = 0;
        }
        $record->fill($data)->save();

        // products_variants_attributes has no primary key: rewrite the variant's rows.
        DB::table('products_variants_attributes')->where('variant_id', $record->id)->delete();
        $rows = [];
        foreach ($attributeValues as $row) {
            if (empty($row['attribute_id']) || ! isset($row['value']) || $row['value'] === '') {
                continue;
            }
            $rows[] = ['product_id' => $record->product_id, 'variant_id' => $record->id, 'attribute_id' => (int) $row['attribute_id'], 'value' => (string) $row['value']];
        }
        if ($rows !== []) {
            DB::table('products_variants_attributes')->insert($rows);
        }

        return $record;
    }

    protected function afterSave(): void
    {
        /** @var ProductVariant $variant */
        $variant = $this->getRecord();
        // Legacy called main_variant() (re-election only); editing the main variant's prices or
        // images must also refresh the product's min/max price and cover, so recompute in that case.
        $product = $variant->product;
        if ($product) {
            if ((int) $product->main_variant_id === (int) $variant->id && (bool) $variant->active) {
                $product->set_main_variant($variant->id, true);
            } else {
                $product->main_variant();
            }
        }
        CatalogCache::flush();
    }
}
