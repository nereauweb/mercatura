<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Tables;

use App\Actions\Catalog\DeleteCategory;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Roots by default (reorderable by position); pick a parent in the filter to order its children. */
final class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('products')->with('parent_category'))
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                ImageColumn::make('icon')->label(__('admin.catalog.icon'))->disk('public')
                    ->getStateUsing(fn (Category $record): ?string => $record->icon ? 'categories/icons/'.$record->icon : null)->square()->size(32),
                TextColumn::make('name')->label(__('admin.catalog.name'))->searchable()->sortable()
                    ->description(fn (Category $record): ?string => $record->parent_category?->name),
                TextColumn::make('slug')->label(__('admin.catalog.slug'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('active')->label(__('admin.catalog.active'))->boolean(),
                TextColumn::make('products_count')->label(__('admin.catalog.products_count'))->sortable(),
                TextColumn::make('position')->label(__('admin.catalog.ordering'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('parent_id')->label(__('admin.catalog.parent'))
                    ->options(fn (): array => ['root' => __('admin.catalog.only_roots')] + Category::query()->whereNull('parent_id')->orderBy('position')->pluck('name', 'id')->all())
                    ->default('root')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'root' => $query->whereNull('parent_id'),
                        default => $query->where('parent_id', (int) $data['value']),
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->using(fn (Category $record) => app(DeleteCategory::class)->handle($record)),
            ]);
    }
}
