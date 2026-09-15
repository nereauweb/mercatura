<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Actions\Catalog\SyncProductCategories;
use App\Exports\AdminProductsExport;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Brand;
use App\Models\Product;
use App\Support\CatalogCache;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;

final class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('categories'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label(__('admin.common.id'))->sortable(),
                ImageColumn::make('cover_url')->label(__('admin.catalog.cover'))->square()->size(40)->defaultImageUrl(asset('/img/logo-mark.png')),
                TextColumn::make('sku')->label(__('admin.catalog.sku'))->searchable()->sortable()->copyable(),
                TextColumn::make('name')->label(__('admin.catalog.name'))->searchable()->sortable()->limit(50)->wrap(),
                TextColumn::make('brand')->label(__('admin.catalog.brand'))->placeholder('-')->toggleable(),
                IconColumn::make('active')->label(__('admin.catalog.active'))->boolean(),
                TextColumn::make('source')->label(__('admin.catalog.source'))->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('variants_min_price')->label(__('admin.catalog.min_price'))->money('EUR')->sortable()->toggleable(),
                TextColumn::make('categories.name')->label(__('admin.catalog.categories'))->badge()->color('gray')->limitList(3),
                TextColumn::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active')->label(__('admin.catalog.active')),
                SelectFilter::make('categories')->label(__('admin.catalog.category'))->relationship('categories', 'name')->searchable()->preload()->multiple(),
                SelectFilter::make('brand_id')->label(__('admin.catalog.brand'))->options(fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                SelectFilter::make('source')->label(__('admin.catalog.source'))->options(fn (): array => Product::query()->select('source')->distinct()->orderBy('source')->pluck('source', 'source')->all()),
            ])
            ->searchable()
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')->label(__('admin.catalog.bulk.activate'))->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => self::setActive($records, true))->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')->label(__('admin.catalog.bulk.deactivate'))->icon('heroicon-o-x-circle')->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => self::setActive($records, false))->deselectRecordsAfterCompletion(),
                    BulkAction::make('assignCategory')->label(__('admin.catalog.bulk.assign_category'))->icon('heroicon-o-tag')
                        ->schema([CheckboxList::make('category_ids')->hiddenLabel()->options(fn (): array => ProductForm::categoryOptions())->columns(2)->searchable()->required()])
                        ->action(function (Collection $records, array $data): void {
                            $ids = array_map('intval', (array) $data['category_ids']);
                            foreach ($records as $product) {
                                if ($product instanceof Product) {
                                    app(SyncProductCategories::class)->handle($product, $ids, replace: false);
                                }
                            }
                            Notification::make()->title(__('admin.catalog.bulk.done', ['count' => $records->count()]))->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('export')->label(__('admin.catalog.bulk.export'))->icon('heroicon-o-arrow-down-tray')->color('gray')
                        ->action(fn (Collection $records) => Excel::download(new AdminProductsExport($records->modelKeys()), 'products.xlsx')),
                ]),
            ]);
    }

    /**
     * Legacy bulk status change (query update, no per-row events), then the
     * search index is refreshed explicitly for the touched products.
     *
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>  $records
     */
    private static function setActive(Collection $records, bool $active): void
    {
        $ids = $records->modelKeys();
        Product::query()->whereIn('id', $ids)->update(['active' => $active ? 1 : 0]);
        Product::query()->whereIn('id', $ids)->get()->each(fn (Product $product) => $product->searchable());
        CatalogCache::flush();
        Notification::make()->title(__('admin.catalog.bulk.done', ['count' => count($ids)]))->success()->send();
    }
}
