<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Catalog\SetMainVariant;
use App\Filament\Resources\ProductVariants\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Support\CatalogCache;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('admin.catalog.variants');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('color_id')->label(__('admin.catalog.color'))->required()
                ->options(fn (): array => ProductColor::query()->orderBy('label')->pluck('label', 'id')->all())->searchable()->native(false),
            Select::make('size_id')->label(__('admin.catalog.size'))
                ->options(fn (): array => ProductSize::query()->orderBy('label')->pluck('label', 'id')->all())
                ->default(fn (): int => (int) config('mercatura.catalog.one_size_id', 52))->searchable()->native(false),
            TextInput::make('sku')->label(__('admin.catalog.sku'))->maxLength(50)->helperText('Vuoto: SKU prodotto + progressivo'),
            TextInput::make('stock')->label(__('admin.catalog.stock'))->numeric()->default(0),
            TextInput::make('price')->label(__('admin.catalog.price'))->numeric()->step(0.01)->prefix('€')->helperText('Prima fascia (da 1 pezzo)'),
            Toggle::make('active')->label(__('admin.catalog.active'))->default(true)->inline(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['color', 'size', 'lowestPrice']))
            ->defaultSort('id')
            ->columns([
                ImageColumn::make('cover')->label(__('admin.catalog.cover'))->square()->size(40)
                    ->getStateUsing(fn (ProductVariant $record): ?string => $record->cover(true) ?: null),
                TextColumn::make('sku')->label(__('admin.catalog.sku'))->searchable(),
                TextColumn::make('color.label')->label(__('admin.catalog.color'))->placeholder('-'),
                TextColumn::make('size.label')->label(__('admin.catalog.size'))->placeholder('-'),
                IconColumn::make('active')->label(__('admin.catalog.active'))->boolean(),
                TextColumn::make('isSale')->label(__('admin.catalog.is_sale'))->formatStateUsing(fn ($state): string => (int) $state > 0 ? 'Sì' : 'No'),
                TextColumn::make('stock')->label(__('admin.catalog.stock')),
                TextColumn::make('lowestPrice.price')->label(__('admin.catalog.min_price'))->money('EUR')->placeholder('-'),
                IconColumn::make('is_main')->label(__('admin.catalog.main_variant'))->boolean()
                    ->getStateUsing(fn (ProductVariant $record): bool => (int) $record->product_id === (int) $this->getOwnerRecord()->getKey() && (int) $this->getOwnerRecord()->getAttribute('main_variant_id') === (int) $record->id),
            ])
            ->headerActions([
                CreateAction::make()->label(__('admin.catalog.variant'))
                    ->using(function (array $data): ProductVariant {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();
                        $variant = ProductVariant::query()->create([
                            'product_id' => $product->id,
                            'sku' => trim((string) ($data['sku'] ?? '')) !== '' ? trim((string) $data['sku']) : $product->sku.'.'.($product->variants()->count() + 1),
                            'source_sku' => trim((string) ($data['sku'] ?? '')) !== '' ? trim((string) $data['sku']) : $product->sku,
                            'active' => ! empty($data['active']) ? 1 : 0, 'isSale' => 0,
                            'color_id' => (int) $data['color_id'], 'size_id' => $data['size_id'] ?? null,
                            'stock' => (int) ($data['stock'] ?? 0), 'source' => strtoupper((string) $product->source),
                        ]);
                        if (isset($data['price']) && is_numeric($data['price'])) {
                            ProductVariantPrice::query()->create(['variant_id' => $variant->id, 'from_quantity' => 1, 'price' => (float) $data['price'], 'original_price' => (float) $data['price'], 'included_additional_costs' => 0]);
                        }
                        $product->main_variant();
                        CatalogCache::flush();

                        return $variant;
                    }),
            ])
            ->recordActions([
                Action::make('edit')->label(__('filament-actions::edit.single.label'))->icon('heroicon-o-pencil-square')
                    ->url(fn (ProductVariant $record): string => ProductVariantResource::getUrl('edit', ['record' => $record])),
                Action::make('setMain')->label(__('admin.catalog.set_main_variant'))->icon('heroicon-o-star')->color('warning')
                    ->visible(fn (ProductVariant $record): bool => (int) $this->getOwnerRecord()->getAttribute('main_variant_id') !== (int) $record->id && (bool) $record->active)
                    ->action(function (ProductVariant $record): void {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();
                        app(SetMainVariant::class)->handle($product, (int) $record->id);
                        Notification::make()->title(__('admin.catalog.set_main_variant_done'))->success()->send();
                    }),
                DeleteAction::make()->after(function (): void {
                    /** @var Product $product */
                    $product = $this->getOwnerRecord();
                    app(SetMainVariant::class)->reelect($product);
                }),
            ]);
    }
}
