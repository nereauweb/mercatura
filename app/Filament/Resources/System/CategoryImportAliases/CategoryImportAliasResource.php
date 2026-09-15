<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\CategoryImportAliases;

use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\System\CategoryImportAliases\Pages\ManageCategoryImportAliases;
use App\Models\CategoryImportAlias;
use App\Support\CatalogCache;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Supplier category references ⇄ shop categories (legacy "mappatura categorie"). Rows come from the connectors; here they are only assigned. */
final class CategoryImportAliasResource extends Resource
{
    protected static ?string $model = CategoryImportAlias::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.imports');
    }

    public static function getModelLabel(): string
    {
        return __('admin.imports.alias');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.imports.aliases');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('imports.run') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')->label(__('admin.imports.alias_category'))->options(fn (): array => ProductForm::categoryOptions())->searchable()->nullable()->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->defaultSort('parent_category_ref')
            ->columns([
                TextColumn::make('source')->label(__('admin.imports.alias_source'))->badge()->color('gray'),
                TextColumn::make('parent_category_ref')->label(__('admin.imports.alias_parent'))->searchable()->sortable(),
                TextColumn::make('category_ref')->label(__('admin.imports.alias_ref'))->searchable()->sortable(),
                TextColumn::make('category.name')->label(__('admin.imports.alias_category'))->placeholder(__('admin.imports.alias_unassigned'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')->label(__('admin.imports.alias_source'))->options(fn (): array => CategoryImportAlias::query()->select('source')->distinct()->orderBy('source')->pluck('source', 'source')->all()),
                TernaryFilter::make('assigned')->label(__('admin.imports.alias_category'))
                    ->queries(true: fn (Builder $q) => $q->whereNotNull('category_id'), false: fn (Builder $q) => $q->whereNull('category_id'), blank: fn (Builder $q) => $q),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('lg')->after(fn () => CatalogCache::flush()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('unlink')->label(__('admin.imports.alias_unlink'))->icon('heroicon-o-x-mark')->color('warning')->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            CategoryImportAlias::query()->whereIn('id', $records->modelKeys())->update(['category_id' => null]);
                            CatalogCache::flush();
                        })->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategoryImportAliases::route('/')];
    }
}
