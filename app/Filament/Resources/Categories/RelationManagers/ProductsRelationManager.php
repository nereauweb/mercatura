<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\CatalogCache;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Products of a category in their display order (category_product.position, legacy update_products_order). */
final class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.catalog.products');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('category_product.position')
            ->reorderable('category_product.position')
            ->columns([
                ImageColumn::make('cover_url')->label(__('admin.catalog.cover'))->square()->size(32),
                TextColumn::make('sku')->label(__('admin.catalog.sku'))->searchable(),
                TextColumn::make('name')->label(__('admin.catalog.name'))->searchable()->limit(60),
                IconColumn::make('active')->label(__('admin.catalog.active'))->boolean(),
            ])
            ->recordActions([
                Action::make('edit')->label(__('filament-actions::edit.single.label'))->icon('heroicon-o-pencil-square')
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
                DetachAction::make(),
            ]);
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);
        CatalogCache::flush();
    }
}
