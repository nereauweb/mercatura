<?php

declare(strict_types=1);

namespace App\Filament\Resources\Brands;

use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Brands\Pages\EditBrand;
use App\Filament\Resources\Brands\Pages\ListBrands;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Brand;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Brands (the table introduced in v2b.0; products.brand keeps the label in sync). */
final class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.catalog');
    }

    public static function getModelLabel(): string
    {
        return __('admin.catalog.brand');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.catalog.brands');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make(__('admin.catalog.tabs.data'))->columns(2)->schema([
                    TextInput::make('name')->label(__('admin.catalog.name'))->required()->maxLength(64)->unique(ignoreRecord: true),
                    TextInput::make('slug')->label(__('admin.catalog.slug'))->maxLength(96)->unique(ignoreRecord: true)->helperText(__('admin.catalog.slug_hint')),
                    Toggle::make('active')->label(__('admin.catalog.active'))->default(true)->inline(false),
                    TextInput::make('position')->label(__('admin.catalog.ordering'))->numeric()->default(0),
                    FileUpload::make('logo')->label(__('admin.catalog.logo'))->image()->disk('public')->directory('brands')->visibility('public')->columnSpanFull(),
                    RichEditor::make('description')->label(__('admin.catalog.description'))->columnSpanFull(),
                ]),
                Tab::make(__('admin.catalog.tabs.seo'))->columns(2)->schema(ProductForm::seoFields()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('products'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('logo')->label(__('admin.catalog.logo'))->disk('public')->square()->size(32)
                    ->getStateUsing(fn (Brand $record): ?string => $record->logo ? ltrim(str_replace('/storage/', '', (string) $record->logo), '/') : null),
                TextColumn::make('name')->label(__('admin.catalog.name'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('admin.catalog.slug'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('active')->label(__('admin.catalog.active'))->boolean(),
                TextColumn::make('products_count')->label(__('admin.catalog.products_count'))->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrands::route('/'),
            'create' => CreateBrand::route('/create'),
            'edit' => EditBrand::route('/{record}/edit'),
        ];
    }
}
