<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\Pricing;

use App\Filament\Resources\System\Pricing\Pages\ManageProductPriceTiers;
use App\Models\ImportData\NormalizedRulesPriceTiers;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Quantity breaks generated at import for a unit-cost range (MarkupRules::tiers). Nothing in the storefront reads them. */
final class ProductPriceTierResource extends Resource
{
    protected static ?string $model = NormalizedRulesPriceTiers::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?int $navigationSort = 31;

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.system');
    }

    public static function getModelLabel(): string
    {
        return __('admin.pricing.tier');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.pricing.tiers');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('from_price')->label(__('admin.pricing.from_cost'))->numeric()->minValue(0)->step(0.01)->required()->suffix('€'),
            TextInput::make('to_price')->label(__('admin.pricing.to_cost'))->numeric()->step(0.01)->required()->suffix('€')->gt('from_price'),
            TextInput::make('from_quantity_1')->label(__('admin.pricing.quantity_n', ['n' => 1]))->integer()->minValue(1)->required(),
            TextInput::make('from_quantity_2')->label(__('admin.pricing.quantity_n', ['n' => 2]))->integer()->required()->gt('from_quantity_1'),
            TextInput::make('from_quantity_3')->label(__('admin.pricing.quantity_n', ['n' => 3]))->integer()->required()->gt('from_quantity_2'),
            TextInput::make('from_quantity_4')->label(__('admin.pricing.quantity_n', ['n' => 4]))->integer()->required()->gt('from_quantity_3'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('from_price')
            ->columns([
                TextColumn::make('from_price')->label(__('admin.pricing.from_cost'))->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->suffix(' €')->sortable(),
                TextColumn::make('to_price')->label(__('admin.pricing.to_cost'))->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->suffix(' €')->sortable(),
                TextColumn::make('from_quantity_1')->label(__('admin.pricing.quantity_n', ['n' => 1])),
                TextColumn::make('from_quantity_2')->label(__('admin.pricing.quantity_n', ['n' => 2])),
                TextColumn::make('from_quantity_3')->label(__('admin.pricing.quantity_n', ['n' => 3])),
                TextColumn::make('from_quantity_4')->label(__('admin.pricing.quantity_n', ['n' => 4])),
            ])
            ->recordActions([EditAction::make()->modalWidth('lg'), DeleteAction::make()])
            ->headerActions([CreateAction::make()->modalWidth('lg')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProductPriceTiers::route('/')];
    }
}
