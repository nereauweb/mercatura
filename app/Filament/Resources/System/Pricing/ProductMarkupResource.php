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
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The markup bands read by App\Support\Connectors\MarkupRules: order value
 * range → percent. Bands may not overlap.
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
            TextInput::make('from_condition')->label(__('admin.pricing.from_value'))->helperText(__('admin.pricing.from_value_hint'))->numeric()->minValue(0)->required()->suffix('€'),
            TextInput::make('to_condition')->label(__('admin.pricing.to_value'))->numeric()->required()->suffix('€')
                ->gt('from_condition')
                ->rule(fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                    $from = (float) $get('from_condition');
                    $overlapping = ProductMarkup::query()
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                        ->where('from_condition', '<', (float) $value)
                        ->where('to_condition', '>', $from)
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
            ->defaultSort('from_condition')
            ->columns([
                TextColumn::make('from_condition')->label(__('admin.pricing.from_value'))->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->suffix(' €')->sortable(),
                TextColumn::make('to_condition')->label(__('admin.pricing.to_value'))->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')->suffix(' €')->sortable(),
                TextColumn::make('value')->label(__('admin.pricing.percent'))->numeric(decimalPlaces: 2, decimalSeparator: ',')->suffix(' %')->sortable(),
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
