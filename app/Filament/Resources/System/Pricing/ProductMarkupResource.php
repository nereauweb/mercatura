<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\Pricing;

use App\Filament\Resources\System\Pricing\Pages\ManageProductMarkups;
use App\Models\ProductMarkup;
use App\Support\CatalogCache;
use BackedEnum;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The markup bands read by App\Support\Connectors\MarkupRules: order value
 * range → percent, in two series (import-time prices, storefront). Bands of
 * one series may not overlap.
 */
final class ProductMarkupResource extends Resource
{
    protected static ?string $model = ProductMarkup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.system');
    }

    public static function getModelLabel(): string
    {
        return __('admin.pricing.markup');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.pricing.markups');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('condition_3')->label(__('admin.pricing.series'))->options(ProductMarkup::seriesOptions())->default(ProductMarkup::SERIES_STOREFRONT)->required()->native(false)->columnSpanFull(),
            TextInput::make('condition_1')->label(__('admin.pricing.from_value'))->helperText(__('admin.pricing.from_value_hint'))->numeric()->minValue(0)->required()->suffix('€'),
            TextInput::make('condition_2')->label(__('admin.pricing.to_value'))->numeric()->required()->suffix('€')
                ->gt('condition_1')
                ->rule(fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                    $from = (float) $get('condition_1');
                    $overlapping = ProductMarkup::query()
                        ->where('condition_3', (int) $get('condition_3'))
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                        ->where('condition_1', '<', (float) $value)
                        ->where('condition_2', '>', $from)
                        ->exists();
                    if ($overlapping) {
                        $fail(__('admin.pricing.overlap'));
                    }
                }),
            TextInput::make('value')->label(__('admin.pricing.percent'))->numeric()->minValue(0)->maxValue(1000)->step(0.01)->required()->suffix('%')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('condition_1')
            ->defaultGroup('condition_3')
            ->columns([
                TextColumn::make('condition_3')->label(__('admin.pricing.series'))->badge()->formatStateUsing(fn ($state): string => ProductMarkup::seriesOptions()[(int) $state] ?? (string) $state)->color(fn ($state): string => (int) $state === ProductMarkup::SERIES_STOREFRONT ? 'primary' : 'gray'),
                TextColumn::make('condition_1')->label(__('admin.pricing.from_value'))->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->suffix(' €')->sortable(),
                TextColumn::make('condition_2')->label(__('admin.pricing.to_value'))->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->suffix(' €')->sortable(),
                TextColumn::make('value')->label(__('admin.pricing.percent'))->numeric(decimalPlaces: 2, decimalSeparator: ',')->suffix(' %')->sortable(),
            ])
            ->groups([
                \Filament\Tables\Grouping\Group::make('condition_3')->label(__('admin.pricing.series'))->getTitleFromRecordUsing(fn (ProductMarkup $record): string => ProductMarkup::seriesOptions()[(int) $record->condition_3] ?? (string) $record->condition_3),
            ])
            ->filters([
                SelectFilter::make('condition_3')->label(__('admin.pricing.series'))->options(ProductMarkup::seriesOptions()),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('lg')->after(fn () => CatalogCache::flush()),
                DeleteAction::make()->after(fn () => CatalogCache::flush()),
            ])
            ->headerActions([CreateAction::make()->modalWidth('lg')->after(fn () => CatalogCache::flush())]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProductMarkups::route('/')];
    }
}
