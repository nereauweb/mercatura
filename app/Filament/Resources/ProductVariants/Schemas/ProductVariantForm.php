<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductVariants\Schemas;

use App\Models\ProductAttribute;
use App\Models\ProductColor;
use App\Models\ProductSize;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ProductVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('admin.catalog.tabs.data'))->columns(3)->components([
                    TextInput::make('sku')->label(__('admin.catalog.sku'))->required()->maxLength(50)->unique(ignoreRecord: true),
                    Select::make('color_id')->label(__('admin.catalog.color'))->required()
                        ->options(fn (): array => ProductColor::query()->orderBy('label')->pluck('label', 'id')->all())->searchable()->native(false),
                    Select::make('size_id')->label(__('admin.catalog.size'))
                        ->options(fn (): array => ProductSize::query()->orderBy('label')->pluck('label', 'id')->all())->searchable()->native(false),
                    Toggle::make('active')->label(__('admin.catalog.active'))->inline(false),
                    Select::make('isSale')->label(__('admin.catalog.is_sale'))->options(['0' => 'No', '1' => 'Sì', '2' => 'Sì (forzato)'])->native(false),
                    TextInput::make('stock')->label(__('admin.catalog.stock'))->numeric()->required()->default(0),
                    TextInput::make('next_stock_quantity')->label(__('admin.catalog.next_stock_quantity'))->numeric()->nullable(),
                    DatePicker::make('next_stock_date')->label(__('admin.catalog.next_stock_date'))->native(false)->displayFormat('d/m/Y'),
                ])->columnSpanFull(),
                Section::make(__('admin.catalog.prices'))->components([
                    Repeater::make('prices')->relationship('prices', modifyQueryUsing: fn ($query) => $query->orderBy('from_quantity'))->hiddenLabel()
                        ->reorderable(false)->defaultItems(0)->columns(4)
                        ->schema([
                            TextInput::make('from_quantity')->label(__('admin.catalog.from_quantity'))->numeric()->required()->minValue(1),
                            TextInput::make('price')->label(__('admin.catalog.price'))->numeric()->step(0.01)->required()->prefix('€'),
                            TextInput::make('original_price')->label(__('admin.catalog.original_price'))->numeric()->step(0.01)->prefix('€'),
                            TextInput::make('included_additional_costs')->label(__('admin.catalog.included_additional_costs'))->numeric()->step(0.01)->default(0)->prefix('€'),
                        ]),
                ])->columnSpanFull(),
                Section::make(__('admin.catalog.images'))->components([
                    SpatieMediaLibraryFileUpload::make('images')->hiddenLabel()->collection('image')->image()->multiple()->reorderable()->panelLayout('grid'),
                ]),
                Section::make(__('admin.catalog.variant_attributes'))->components([
                    Repeater::make('attribute_values')->hiddenLabel()->defaultItems(0)->columns(2)
                        ->schema([
                            Select::make('attribute_id')->label(__('admin.catalog.attribute'))->required()
                                ->options(fn (): array => ProductAttribute::query()->orderBy('label')->pluck('label', 'id')->all())->native(false),
                            TextInput::make('value')->label(__('admin.catalog.value'))->required(),
                        ]),
                ]),
                Section::make(__('admin.catalog.printings'))->columnSpanFull()->collapsed()->components([
                    RepeatableEntry::make('customizations')->hiddenLabel()->columns(4)->placeholder('-')->components([
                        TextEntry::make('technique_label')->label(__('admin.catalog.technique')),
                        TextEntry::make('position_label')->label(__('admin.catalog.position')),
                        TextEntry::make('minimum_quantity')->label(__('admin.catalog.from_quantity')),
                        TextEntry::make('is_default')->label(__('admin.catalog.main_variant'))->formatStateUsing(fn ($state): string => $state ? 'Sì' : 'No'),
                    ]),
                ]),
            ]);
    }
}
