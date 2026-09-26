<?php

declare(strict_types=1);

namespace App\Filament\Resources\Taxonomy;

use App\Models\ProductColor;
use App\Support\CatalogCache;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Colours (hex code, any number of families). Rows in use cannot be deleted. */
final class ProductColorResource extends Resource
{
    protected static ?string $model = ProductColor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'label';

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.catalog');
    }

    public static function getModelLabel(): string
    {
        return __('admin.catalog.color');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.catalog.colors');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('label')->label(__('admin.catalog.label'))->required()->maxLength(128),
            TextInput::make('code')->label(__('admin.catalog.code'))->maxLength(512)->helperText(__('admin.catalog.code_hint')),
            Select::make('families')->label(__('admin.catalog.families'))->relationship('families', 'label')->multiple()->preload()->searchable()->native(false)->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('variants'))
            ->defaultSort('label')
            ->columns([
                TextColumn::make('id')->label(__('admin.common.id'))->sortable(),
                TextColumn::make('label')->label(__('admin.catalog.label'))->searchable()->sortable(),
                TextColumn::make('code')->label(__('admin.catalog.code'))->placeholder('-'),
                TextColumn::make('families.label')->label(__('admin.catalog.families'))->badge()->placeholder('-'),
                TextColumn::make('variants_count')->label(__('admin.catalog.in_use_count'))->sortable(),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('lg'),
                DeleteAction::make()->before(function (DeleteAction $action, ProductColor $record): void {
                    $count = $record->variants()->count();
                    if ($count > 0) {
                        Notification::make()->title(__('admin.catalog.in_use', ['count' => $count]))->danger()->send();
                        $action->cancel();
                    }
                })->after(fn () => CatalogCache::flush()),
            ])
            ->headerActions([CreateAction::make()->modalWidth('lg')->after(fn () => CatalogCache::flush())]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Taxonomy\Pages\ManageProductColors::route('/'),
        ];
    }
}
